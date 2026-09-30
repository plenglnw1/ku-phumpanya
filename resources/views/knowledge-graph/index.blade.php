<x-layouts.phumpanya :recent-searches="$recentSearches" :active-nav="'graph'" title="Knowledge Graph">
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">BCG Knowledge Graph</h1>
            <p class="mt-1 text-sm text-gray-500">
                Neo4j graph of KU Forest research, KUKR documents and KU MOOC courses, linked by the BCG Education ontology.
                Read-only.
            </p>
        </div>

        @unless ($configured)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                The knowledge graph database is not configured on this server (KG_ENABLED / KG_NEO4J_PASSWORD).
            </div>
        @endunless

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-kg-action="overview" class="rounded-lg bg-phumpanya-900 px-3 py-2 text-xs font-semibold text-white hover:bg-phumpanya-800">Concept layer</button>
                    <button type="button" data-kg-action="carbon" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Carbon Footprint hub</button>
                    <button type="button" data-kg-action="clear" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Clear</button>
                    <form data-kg-search class="ml-auto flex items-center gap-2">
                        <input type="search" name="q" minlength="2" maxlength="100" placeholder="Search nodes…" class="w-56 rounded-lg border-gray-200 text-sm focus:border-phumpanya-700 focus:ring-phumpanya-700">
                        <button type="submit" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Search</button>
                    </form>
                </div>
                <div class="relative">
                    <div data-kg-canvas class="h-[560px] rounded-xl border border-gray-200 bg-gray-50/50"></div>
                    <p data-kg-status class="absolute left-3 top-3 rounded bg-white/90 px-2 py-1 text-xs text-gray-500"></p>
                </div>
                <p class="text-xs text-gray-400">Double-click a node to expand its neighbours. Click a node to see its properties.</p>
            </div>

            <aside class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-gray-900">Legend</h2>
                    <ul data-kg-legend class="mt-2 grid grid-cols-2 gap-1 text-xs text-gray-600"></ul>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-gray-900">Search results</h2>
                    <ul data-kg-results class="mt-2 max-h-48 space-y-1 overflow-y-auto text-xs text-gray-600">
                        <li class="text-gray-400">None yet</li>
                    </ul>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <h2 class="text-sm font-semibold text-gray-900">Selected node</h2>
                    <dl data-kg-details class="mt-2 max-h-72 space-y-1 overflow-y-auto break-words text-xs text-gray-600">
                        <dd class="text-gray-400">Click a node</dd>
                    </dl>
                </div>
            </aside>
        </div>

        <section class="rounded-xl border border-gray-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-gray-900">Cypher (read-only)</h2>
            <p class="mt-1 text-xs text-gray-500">
                MATCH … RETURN only. Up to {{ config('knowledge_graph.row_limit') }} rows; queries stop after 15 seconds.
            </p>
            <form data-kg-cypher class="mt-3 space-y-2">
                <textarea name="query" rows="3" maxlength="{{ \App\Services\KnowledgeGraph\CypherGuard::MAX_LENGTH }}" class="w-full rounded-lg border-gray-200 font-mono text-xs focus:border-phumpanya-700 focus:ring-phumpanya-700">MATCH (f:Faculty)-[r:mitigatesCarbonVia]->(t:Topic) RETURN f, r, t</textarea>
                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-lg bg-phumpanya-900 px-3 py-2 text-xs font-semibold text-white hover:bg-phumpanya-800">Run</button>
                    <p data-kg-cypher-status class="text-xs text-gray-500"></p>
                </div>
            </form>
            <div data-kg-table class="mt-3 max-h-80 overflow-auto"></div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-gray-900">Validation reports</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($reports as $report)
                    <button type="button" data-kg-report-tab="{{ $report['key'] }}" class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50">{{ $report['title'] }}</button>
                @endforeach
            </div>
            @foreach ($reports as $report)
                <article data-kg-report="{{ $report['key'] }}" @class(['kg-report mt-4 max-h-[600px] overflow-auto text-sm text-gray-700', 'hidden' => ! $loop->first])>
                    @if ($report['html'] !== null)
                        {!! $report['html'] !!}
                    @else
                        <p class="text-gray-400">Not available yet{{ $report['key'] === 'ioc' ? ' (waiting for the three experts\' ratings)' : '' }}.</p>
                    @endif
                </article>
            @endforeach
        </section>
    </div>

    @push('head')
        <style>
            .kg-report h1 { font-size: 1.125rem; font-weight: 700; margin: 0 0 .5rem; }
            .kg-report h2 { font-size: 1rem; font-weight: 600; margin: 1.25rem 0 .5rem; }
            .kg-report p, .kg-report ul { margin: .5rem 0; }
            .kg-report ul { list-style: disc; padding-left: 1.25rem; }
            .kg-report table { border-collapse: collapse; margin: .5rem 0; font-size: .75rem; }
            .kg-report th, .kg-report td { border: 1px solid #e5e7eb; padding: .25rem .5rem; text-align: left; }
            .kg-report th { background: #f9fafb; }
            .kg-report code { font-size: .75rem; background: #f3f4f6; padding: 0 .25rem; border-radius: .25rem; }
        </style>
    @endpush

    @push('scripts')
        <script src="https://unpkg.com/vis-network@9.1.9/standalone/umd/vis-network.min.js"
                integrity="sha384-yxKDWWf0wwdUj/gPeuL11czrnKFQROnLgY8ll7En9NYoXibgg3C6NK/UDHNtUgWJ"
                crossorigin="anonymous"></script>
        <script>
            (() => {
                const routes = {
                    overview: @json(route('graph.overview')),
                    search: @json(route('graph.search')),
                    neighbours: @json(route('graph.neighbours')),
                    cypher: @json(route('graph.cypher')),
                };
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const colors = {
                    Topic: '#2D5A43', Faculty: '#b45309', BCGPillar: '#15803d', Course: '#7c3aed', Keyword: '#0369a1',
                    Concept: '#0e7490', Research: '#be123c', Document: '#a16207', Author: '#6b7280', Department: '#9ca3af',
                };
                const $ = (selector) => document.querySelector(selector);
                const status = $('[data-kg-status]');

                const legend = $('[data-kg-legend]');
                Object.entries(colors).forEach(([label, color]) => {
                    const item = document.createElement('li');
                    item.className = 'flex items-center gap-1.5';
                    const dot = document.createElement('span');
                    dot.className = 'inline-block h-2.5 w-2.5 rounded-full';
                    dot.style.background = color;
                    item.append(dot, label);
                    legend.append(item);
                });

                if (typeof vis === 'undefined') {
                    status.textContent = 'Graph library failed to load.';
                    return;
                }

                const nodes = new vis.DataSet();
                const edges = new vis.DataSet();
                const byUid = new Map();
                const network = new vis.Network($('[data-kg-canvas]'), { nodes, edges }, {
                    nodes: { shape: 'dot', size: 12, font: { size: 12, face: 'Figtree, sans-serif' } },
                    edges: { arrows: { to: { enabled: true, scaleFactor: 0.5 } }, font: { size: 9, align: 'middle', color: '#6b7280' }, color: '#cbd5e1', smooth: { type: 'dynamic' } },
                    physics: { stabilization: { iterations: 150 }, barnesHut: { gravitationalConstant: -4000, springLength: 140 } },
                    interaction: { hover: true, tooltipDelay: 150 },
                });

                const request = async (url, options = {}) => {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json' },
                        ...options,
                    });
                    const body = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(body.message || `HTTP ${response.status}`);
                    }
                    return body;
                };

                const addGraph = (graph) => {
                    nodes.update(graph.nodes.map((node) => {
                        if (node.uid) byUid.set(node.id, node);
                        const short = node.name.length > 40 ? `${node.name.slice(0, 38)}…` : node.name;
                        return {
                            id: node.id, label: short, title: `${node.label}: ${node.name}`,
                            color: colors[node.label] || '#94a3b8', raw: node,
                            size: ['Topic', 'Faculty'].includes(node.label) ? 20 : 12,
                        };
                    }));
                    edges.update(graph.edges.map((edge) => ({ id: edge.id, from: edge.from, to: edge.to, label: edge.type })));
                    status.textContent = `${nodes.length} nodes, ${edges.length} relationships${graph.truncated ? ' (result truncated)' : ''}`;
                };

                const run = async (promise) => {
                    status.textContent = 'Loading…';
                    try {
                        addGraph(await promise);
                    } catch (error) {
                        status.textContent = error.message;
                    }
                };

                const expand = (uid) => run(request(`${routes.neighbours}?uid=${encodeURIComponent(uid)}`));

                const showDetails = (node) => {
                    const details = $('[data-kg-details]');
                    details.replaceChildren();
                    const rows = [['label', node.label], ...Object.entries(node.properties)];
                    rows.forEach(([key, value]) => {
                        const term = document.createElement('dt');
                        term.className = 'font-semibold text-gray-800';
                        term.textContent = key;
                        const description = document.createElement('dd');
                        const text = Array.isArray(value) ? value.join(', ') : String(value);
                        if (/^https?:\/\//.test(text)) {
                            const link = document.createElement('a');
                            link.href = text;
                            link.target = '_blank';
                            link.rel = 'noopener noreferrer';
                            link.className = 'text-phumpanya-800 underline';
                            link.textContent = text;
                            description.append(link);
                        } else {
                            description.textContent = text;
                        }
                        details.append(term, description);
                    });
                };

                network.on('click', ({ nodes: picked }) => {
                    const node = picked.length ? nodes.get(picked[0]) : null;
                    if (node) showDetails(node.raw);
                });
                network.on('doubleClick', ({ nodes: picked }) => {
                    const node = picked.length ? nodes.get(picked[0]) : null;
                    if (node && node.raw.uid) expand(node.raw.uid);
                });

                document.querySelectorAll('[data-kg-action]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const action = button.dataset.kgAction;
                        nodes.clear();
                        edges.clear();
                        if (action === 'overview') run(request(routes.overview));
                        if (action === 'carbon') expand('topic:4');
                        if (action === 'clear') status.textContent = '';
                    });
                });

                $('[data-kg-search]').addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const q = new FormData(event.target).get('q');
                    const list = $('[data-kg-results]');
                    list.replaceChildren();
                    try {
                        const result = await request(`${routes.search}?q=${encodeURIComponent(q)}`);
                        if (!result.nodes.length) {
                            list.textContent = 'No matches';
                        }
                        result.nodes.forEach((node) => {
                            const item = document.createElement('li');
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'w-full truncate rounded px-1 py-0.5 text-left hover:bg-gray-100';
                            button.textContent = `${node.label} · ${node.name}`;
                            button.title = node.name;
                            button.addEventListener('click', () => { if (node.uid) expand(node.uid); showDetails(node); });
                            item.append(button);
                            list.append(item);
                        });
                    } catch (error) {
                        list.textContent = error.message;
                    }
                });

                const renderTable = (result) => {
                    const holder = $('[data-kg-table]');
                    holder.replaceChildren();
                    if (!result.rows.length) return;
                    const table = document.createElement('table');
                    table.className = 'min-w-full border-collapse text-xs';
                    const head = table.createTHead().insertRow();
                    result.columns.forEach((column) => {
                        const cell = document.createElement('th');
                        cell.className = 'border border-gray-200 bg-gray-50 px-2 py-1 text-left font-semibold';
                        cell.textContent = column;
                        head.append(cell);
                    });
                    const body = table.createTBody();
                    result.rows.forEach((row) => {
                        const tr = body.insertRow();
                        row.forEach((value) => {
                            const cell = tr.insertCell();
                            cell.className = 'border border-gray-200 px-2 py-1 align-top';
                            cell.textContent = typeof value === 'object' && value !== null
                                ? (value.name || value.title || JSON.stringify(value))
                                : String(value);
                        });
                    });
                    holder.append(table);
                };

                $('[data-kg-cypher]').addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const cypherStatus = $('[data-kg-cypher-status]');
                    cypherStatus.textContent = 'Running…';
                    try {
                        const result = await request(routes.cypher, {
                            method: 'POST',
                            body: JSON.stringify({ query: new FormData(event.target).get('query') }),
                        });
                        cypherStatus.textContent = `${result.rows.length} rows${result.truncated ? ' (truncated)' : ''}`;
                        renderTable(result);
                        if (result.nodes.length) {
                            nodes.clear();
                            edges.clear();
                            addGraph(result);
                        }
                    } catch (error) {
                        cypherStatus.textContent = error.message;
                    }
                });

                document.querySelectorAll('[data-kg-report-tab]').forEach((tab) => {
                    tab.addEventListener('click', () => {
                        document.querySelectorAll('[data-kg-report]').forEach((report) => {
                            report.classList.toggle('hidden', report.dataset.kgReport !== tab.dataset.kgReportTab);
                        });
                    });
                });

                run(request(routes.overview));
            })();
        </script>
    @endpush
</x-layouts.phumpanya>
