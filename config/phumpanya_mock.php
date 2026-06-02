<?php

declare(strict_types=1);

return [
    'suggestions' => [
        'Analysis of Carbon Nanotubes',
        'Quantum Computing Trends',
        'Impact of AI in Medicine',
    ],

    'topics' => [
        'impact-of-ai-in-medicine' => [
            'title' => 'Impact of AI in Medicine',
            'overview' => [
                'intro' => 'Artificial intelligence is reshaping clinical workflows—from diagnostic imaging to drug discovery. This overview summarizes key research themes and expert perspectives for university researchers.',
                'analogy' => 'Think of clinical AI as a co-pilot: it augments clinician judgment rather than replacing the human relationship at the center of care.',
                'research_basis' => 'Research basis: Topol (2019); Rajpurkar et al. (2022) on deep learning for medical imaging.',
                'expert' => 'Expert / Professor: Dr. Eric Topol (Scripps Research)',
            ],
            'knowledge_graph' => [
                'center' => ['label' => 'AI in Medicine', 'color' => '#2D5A43'],
                'description' => 'Knowledge Graph mapping key research areas connecting clinicians, datasets, and clinical AI applications.',
                'nodes' => [
                    ['label' => 'Diagnostic Imaging', 'color' => '#EAB308'],
                    ['label' => 'Clinical NLP', 'color' => '#F472B6'],
                    ['label' => 'Drug Discovery', 'color' => '#A855F7'],
                    ['label' => 'EHR Analytics', 'color' => '#3B82F6'],
                    ['label' => 'Ethics & Bias', 'color' => '#22C55E'],
                    ['label' => 'Robotics Surgery', 'color' => '#86EFAC'],
                ],
                'edges' => [
                    ['from' => 'Dr. Somchai V.', 'to' => 'Diagnostic Imaging', 'type' => 'leads'],
                    ['from' => 'Dr. Somchai V.', 'to' => 'Clinical NLP', 'type' => 'collaborates'],
                    ['from' => 'KU Med AI Lab', 'to' => 'EHR Analytics', 'type' => 'publishes'],
                ],
            ],
            'learning_path' => [
                'estimated_hours' => '300–500',
                'subtitle' => 'Total estimated time · zero to professional level',
                'progress' => 35,
                'phases' => [
                    [
                        'name' => 'Phase 1: Foundation',
                        'intro' => 'Build clinical and statistical literacy before applying ML to healthcare data.',
                        'modules' => [
                            ['title' => 'Biostatistics Essentials', 'hours' => '10–15 hrs', 'desc' => 'Distributions, hypothesis testing, study design'],
                            ['title' => 'Healthcare Data Standards', 'hours' => '10–15 hrs', 'desc' => 'HL7 FHIR, OMOP, de-identification basics'],
                        ],
                    ],
                    [
                        'name' => 'Phase 2: ML for Health',
                        'intro' => 'Apply supervised and deep learning to structured and imaging data.',
                        'modules' => [
                            ['title' => 'Medical Imaging AI', 'hours' => '15–20 hrs', 'desc' => 'CNNs, segmentation, DICOM pipelines'],
                            ['title' => 'Clinical NLP', 'hours' => '10–15 hrs', 'desc' => 'NER on clinical notes, coding systems'],
                        ],
                    ],
                ],
            ],
        ],

        'quantum-computing-trends' => [
            'title' => 'Quantum Computing Trends',
            'overview' => [
                'intro' => 'Quantum computing continues to advance in qubit stability, error correction, and hybrid classical-quantum algorithms relevant to optimization and cryptography.',
                'analogy' => 'Like exploring a new computational continent—classical tools still map the shoreline while quantum hardware probes deeper structure.',
                'research_basis' => 'Research basis: Preskill (2018); recent surveys on NISQ-era algorithms (2024).',
                'expert' => 'Expert / Professor: Prof. John Preskill (Caltech)',
            ],
            'knowledge_graph' => [
                'center' => ['label' => 'Quantum Computing', 'color' => '#2D5A43'],
                'description' => 'Knowledge Graph linking hardware paradigms, algorithms, and KU research groups.',
                'nodes' => [
                    ['label' => 'Qubits & Error Correction', 'color' => '#EAB308'],
                    ['label' => 'Quantum Algorithms', 'color' => '#F472B6'],
                    ['label' => 'Cryptography', 'color' => '#A855F7'],
                    ['label' => 'Materials Science', 'color' => '#3B82F6'],
                    ['label' => 'HPC Integration', 'color' => '#22C55E'],
                    ['label' => 'Industry Partners', 'color' => '#86EFAC'],
                ],
                'edges' => [
                    ['from' => 'Asst. Prof. Wipawee L.', 'to' => 'Quantum Algorithms', 'type' => 'leads'],
                    ['from' => 'KU Quantum Lab', 'to' => 'Qubits & Error Correction', 'type' => 'publishes'],
                ],
            ],
            'learning_path' => [
                'estimated_hours' => '200–320',
                'subtitle' => 'Total estimated time · foundations to research readiness',
                'progress' => 20,
                'phases' => [
                    [
                        'name' => 'Phase 1: Linear Algebra & QC Basics',
                        'intro' => 'Mathematical foundations for quantum states and gates.',
                        'modules' => [
                            ['title' => 'Linear Algebra Refresh', 'hours' => '12–18 hrs', 'desc' => 'Hilbert space, unitary transforms'],
                            ['title' => 'Intro to Quantum Circuits', 'hours' => '10–14 hrs', 'desc' => 'Qubits, entanglement, measurement'],
                        ],
                    ],
                ],
            ],
        ],

        'analysis-of-carbon-nanotubes' => [
            'title' => 'Analysis of Carbon Nanotubes',
            'overview' => [
                'intro' => 'Carbon nanotubes offer exceptional mechanical and electrical properties for sensors, composites, and nanoelectronics research at KU.',
                'analogy' => 'Imagine a rolled sheet of graphene—geometry at the nanoscale dictates macro-scale material behavior.',
                'research_basis' => 'Research basis: Saito et al. on CNT spectroscopy; recent KU materials science reports.',
                'expert' => 'Expert / Professor: Prof. Mildred Dresselhaus (legacy reference corpus)',
            ],
            'knowledge_graph' => [
                'center' => ['label' => 'Carbon Nanotubes', 'color' => '#2D5A43'],
                'description' => 'Researchers, synthesis methods, and application domains connected via collaboration edges.',
                'nodes' => [
                    ['label' => 'Synthesis', 'color' => '#EAB308'],
                    ['label' => 'Characterization', 'color' => '#F472B6'],
                    ['label' => 'Composites', 'color' => '#A855F7'],
                    ['label' => 'Electronics', 'color' => '#3B82F6'],
                    ['label' => 'Toxicology', 'color' => '#22C55E'],
                    ['label' => 'KU Nano Center', 'color' => '#86EFAC'],
                ],
                'edges' => [
                    ['from' => 'Dr. Pongpat P.', 'to' => 'Synthesis', 'type' => 'leads'],
                    ['from' => 'Dr. Pongpat P.', 'to' => 'Characterization', 'type' => 'supervises'],
                ],
            ],
            'learning_path' => [
                'estimated_hours' => '120–180',
                'subtitle' => 'Total estimated time · materials science track',
                'progress' => 15,
                'phases' => [
                    [
                        'name' => 'Phase 1: Nanomaterials Intro',
                        'intro' => 'Understand bonding, structure, and characterization tooling.',
                        'modules' => [
                            ['title' => 'Solid State Basics', 'hours' => '8–12 hrs', 'desc' => 'Crystal structure, band theory intro'],
                            ['title' => 'Microscopy & Spectroscopy', 'hours' => '10–14 hrs', 'desc' => 'TEM, Raman for CNTs'],
                        ],
                    ],
                ],
            ],
        ],

        'semantic-html' => [
            'title' => 'Semantic HTML',
            'overview' => [
                'intro' => 'Semantic HTML uses meaningful elements so machines and assistive technologies understand document structure—not just visual layout.',
                'analogy' => 'Think of it this way: a <div> tells the browser "this is a box"; <article> tells everyone "this is a self-contained piece of content."',
                'research_basis' => 'Research basis: Bowen (2008) on cognitive load and screen readers; W3C HTML Living Standard.',
                'expert' => 'Expert / Professor: Jeffrey Zeldman (A Book Apart)',
            ],
            'knowledge_graph' => [
                'center' => ['label' => 'HTML Semantics', 'color' => '#2D5A43'],
                'description' => 'Knowledge Graph mapping the 6 key areas of Semantic HTML and linked researchers.',
                'nodes' => [
                    ['label' => 'Navigation & Links', 'color' => '#EAB308'],
                    ['label' => 'Text Semantics', 'color' => '#F472B6'],
                    ['label' => 'Forms & Inputs', 'color' => '#A855F7'],
                    ['label' => 'Media & Tables', 'color' => '#3B82F6'],
                    ['label' => 'ARIA Roles', 'color' => '#22C55E'],
                    ['label' => 'Structural Elements', 'color' => '#86EFAC'],
                ],
                'edges' => [
                    ['from' => 'Prof. Anchalee K.', 'to' => 'Structural Elements', 'type' => 'teaches'],
                    ['from' => 'Prof. Anchalee K.', 'to' => 'ARIA Roles', 'type' => 'researches'],
                    ['from' => 'KU Web Accessibility Group', 'to' => 'Forms & Inputs', 'type' => 'publishes'],
                ],
            ],
            'learning_path' => [
                'estimated_hours' => '80–120',
                'subtitle' => 'Total estimated time · accessible front-end foundations',
                'progress' => 60,
                'phases' => [
                    [
                        'name' => 'Phase 1: Foundation',
                        'intro' => 'Before designing, understand the medium — like a painter must know the canvas.',
                        'modules' => [
                            ['title' => 'HTML Semantics', 'hours' => '10–15 hrs', 'desc' => 'Web structure, semantic tags, accessibility basics'],
                            ['title' => 'CSS Fundamentals', 'hours' => '10–15 hrs', 'desc' => 'Box model, selectors, cascade, specificity'],
                        ],
                    ],
                    [
                        'name' => 'Phase 2: Visual Design Principles',
                        'intro' => 'Design theory backed by research — not just making things look nice.',
                        'modules' => [
                            ['title' => 'Color Theory', 'hours' => '10–15 hrs', 'desc' => 'Color psychology, color harmony'],
                            ['title' => 'Typography', 'hours' => '10–15 hrs', 'desc' => 'Type scale, readability, font pairing'],
                        ],
                    ],
                ],
            ],
        ],

        'default' => [
            'title' => 'Your discovery',
            'overview' => [
                'intro' => 'This is a mock summary generated for your query. In production, the AI backend will synthesize evidence from multiple university databases.',
                'analogy' => 'Exploration starts with a question—Phumpanya maps sources, experts, and learning paths around it.',
                'research_basis' => 'Research basis: curated mock corpus for UAT demonstration.',
                'expert' => 'Expert / Professor: KU Research Commons',
            ],
            'knowledge_graph' => [
                'center' => ['label' => 'Your Topic', 'color' => '#2D5A43'],
                'description' => 'Knowledge Graph showing how researchers and sub-topics connect to your query.',
                'nodes' => [
                    ['label' => 'Related Work A', 'color' => '#EAB308'],
                    ['label' => 'Related Work B', 'color' => '#F472B6'],
                    ['label' => 'Methods', 'color' => '#A855F7'],
                    ['label' => 'Datasets', 'color' => '#3B82F6'],
                    ['label' => 'KU Faculty', 'color' => '#22C55E'],
                    ['label' => 'Publications', 'color' => '#86EFAC'],
                ],
                'edges' => [
                    ['from' => 'KU Researcher', 'to' => 'Related Work A', 'type' => 'authored'],
                    ['from' => 'KU Researcher', 'to' => 'Methods', 'type' => 'develops'],
                ],
            ],
            'learning_path' => [
                'estimated_hours' => '100–200',
                'subtitle' => 'Total estimated time · personalized mock path',
                'progress' => 10,
                'phases' => [
                    [
                        'name' => 'Phase 1: Orientation',
                        'intro' => 'Survey the landscape before diving into specialized modules.',
                        'modules' => [
                            ['title' => 'Topic Survey', 'hours' => '5–8 hrs', 'desc' => 'Read landmark papers and KU holdings'],
                            ['title' => 'Methods Primer', 'hours' => '8–12 hrs', 'desc' => 'Core techniques in this field'],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'smart_picks' => [
        'explanation' => 'Why these? You\'ve completed Smart Farming Phase 2 + showed interest in BCG topics. The model weighted these factors at 65% / 35%.',
        'picks' => [
            [
                'title' => 'Climate-Smart Agriculture',
                'match' => 94,
                'meta' => '~80 hrs · 5 phases · KU Forest',
                'tags' => ['Builds on Smart Farming', '+4 connections'],
                'featured' => true,
            ],
            [
                'title' => 'Aquaculture & Fisheries Tech',
                'match' => 87,
                'meta' => '~120 hrs · 6 phases · KU-KR + KU MOOC',
                'tags' => ['Cross-faculty', 'BCG-Bio'],
                'featured' => false,
            ],
            [
                'title' => 'Forest Carbon Credit Systems',
                'match' => 81,
                'meta' => '~60 hrs · 4 phases · KU Forest (RDIKU)',
                'tags' => ['BCG-Green', 'Trending'],
                'featured' => false,
            ],
        ],
        'filters' => [
            'More like Smart Farming',
            'Shorter paths',
            'Different faculty',
            'Beginner level',
        ],
    ],
];
