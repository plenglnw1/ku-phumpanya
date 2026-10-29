# Ontology and knowledge graph schema

Two layers describe the KU BCG knowledge:

1. **Conceptual model (OWL 2).** It defines classes, properties, domains/ranges, and disjointness. It is validated by experts (IOC) and with OWL 2 RL consistency checks (gate 1.2.5).
2. **Production graph (Neo4j on the KU host).** This is what `/graph` and the Cypher console query. The counts below were read from production on 2026-10-29.

The GraphRAG search pipeline does not query Neo4j. It reads documents from the Elasticsearch index `ku_bcg_documents`, and relation triples from `ku_bcg_relations`, which hold the same seed predicates as the `Entity` layer below.

## Sources of truth

| Artifact | Location |
|----------|----------|
| OWL ontology (Turtle), namespace `https://phumpanya.ku.ac.th/ontology/bcg-education#` | KU-BCG `knowledge-graph/ontology/bcg-education.ttl` (branch `feat/knowledge-graph`) |
| OWL ontology (RDF/XML, corpus-level classes) | KU-BCG `elastic-search/ku-phumpanya-ontology.owl` |
| Graph builder and validators (`build_graph.py`, `validate_graph.py`, `validate_owl.py`, `compute_ioc.py`) | KU-BCG `knowledge-graph/` (branch `feat/knowledge-graph`) |
| Seed topics, faculties, pillars, courses, triples | KU-BCG `elastic-search/seed-data.json` |
| Topic id → pilot faculties, used for result tags | `config/bcg_topics.php` |

## Conceptual classes

```
KnowledgeResource ─┬─ Research   (KU Forest)
                   ├─ Document   (KUKR)
                   └─ Course     (KU MOOC)
Topic · Faculty · Department · BCGPillar · Keyword · Concept · Author
```

- **Disjointness:** the top-level classes are pairwise disjoint, and so are `Research`, `Document`, and `Course`.
- **Structural properties:**
  - `hasPillar` (Topic → BCGPillar)
  - `involvesFaculty` (Topic → Faculty)
  - `hasKeyword`
  - `about` (Research/Document → Topic)
  - `teaches` (Course → Topic)
  - `authored`, `affiliatedWith`, `inDepartment`, `partOf`, `hasExpertise`, `mechanismOf`
- **Cross-disciplinary seed predicates:**
  - `mitigatesCarbonVia`, `providesRawMaterialTo`, `connectsViaValueChain`
  - `isFeedstockOf`, `pollutesVia`, `enhancesGrowthOf`, `integratesSpecies`
  - `requires`, `isKeywordOf`
  - `isEntryPointFor`, `isLaunchingNodeFor`, `expandsEconomicsPerspectiveOf`
- **Data properties:** `name`, `url`, `source`.

### Seed ontology (`ku-phumpanya-ontology.owl`)

The RDF/XML file in KU-BCG `elastic-search/` is the seed-level model that the Elasticsearch seed data comes from. Its IRI is `https://kuphumpanya.ku.ac.th/ontology/ske-sdl.owl`, and it holds 138 named individuals: the 6 topics, the pillars, the faculties, the courses, and the keywords.

| Kind | Name | Domain → range |
|------|------|----------------|
| Class | `Topic`, `BCGPillar`, `Faculty`, `MOOCCourse`, `Keyword` | |
| Object property | `hasBCGPillar` | Topic → BCGPillar |
| Object property | `hasFaculty` | Topic → Faculty |
| Object property | `hasKeyword` | Topic → Keyword |
| Object property | `offersCourse` | Topic → MOOCCourse |
| Object property | `isKeywordOf` | Keyword → Thing |
| Object property | `isEntryPointFor` | MOOCCourse → Thing |

`bcg-education.ttl` extends this model. It adds the resource classes (Research, Document, Course), authors, departments, concepts, disjointness axioms, and the full set of seed predicates.

## Production graph (Neo4j)

### Nodes

| Label | Count | Key properties | Ontology class |
|-------|------:|----------------|----------------|
| `Keyword` | 6,251 | `name` | Keyword |
| `Author` | 2,408 | `name` | Author |
| `Document` | 403 | `id`, `title`, `year`, `url` | Document (KUKR) |
| `Research` | 203 | `id`, `title`, `year`, `url` | Research (KU Forest) |
| `Entity` | 34 | `name` | Concept / Faculty in seed triples |
| `Course` | 6 | | Course (KU MOOC) |
| `Topic` | 6 | | Topic (6 pilot BCG topics) |
| `Faculty` | 4 | | Faculty: วนศาสตร์, เกษตร, อุตสาหกรรมเกษตร, ประมง |
| `BcgPillar` | 3 | | BCGPillar: Bio, Circular, Green |

### Relationships

| Pattern | Count | Ontology property |
|---------|------:|-------------------|
| `(Document\|Research)-[:HAS_KEYWORD]->(Keyword)` | 11,210 | hasKeyword |
| `(Topic)-[:HAS_KEYWORD]->(Keyword)` | 45 | hasKeyword |
| `(Document\|Research)-[:WRITTEN_BY]->(Author)` | 3,282 | authored (inverse) |
| `(Document\|Research)-[:TAGGED_BCG]->(BcgPillar)` | 1,328 | (resource-level pillar) |
| `(Document\|Research)-[:BELONGS_TO_TOPIC]->(Topic)` | 625 | about |
| `(Topic)-[:RELATES_TO_FACULTY]->(Faculty)` | 18 | involvesFaculty |
| `(Topic)-[:HAS_BCG_PILLAR]->(BcgPillar)` | 12 | hasPillar |
| `(Course)-[:OFFERED_FOR_TOPIC]->(Topic)` | 9 | teaches |
| `(Entity)-[:<seed predicate>]->(Entity)` | 23 | The cross-disciplinary seed predicates; `mitigatesCarbonVia` has 8 |

The hub concept for gate 1.2 is `(:Entity {name: 'CarbonFootprint'})`, which `GET /graph/hub` uses.

## Query safety

- `/graph/*` routes need the `view-knowledge-graph` ability.
- The Cypher console (`POST /graph/cypher`, throttled to 30 requests a minute) goes through `App\Services\KnowledgeGraph\CypherGuard`. It accepts `MATCH … RETURN` queries only, rejects `CALL`, `LOAD`, `USE`, `CREATE`, `MERGE`, `DELETE`, `DETACH`, `SET`, and `REMOVE`, and caps queries at 2,000 characters.
- `Neo4jClient::read` sends every query with `Access-Mode: READ`.
