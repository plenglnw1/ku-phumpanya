<x-filament-panels::page>
    @php
        $graph = $this->graph;
        $record = $this->replayRecord;
        $panel = $this->panel;
        $typeColors = [
            'topic' => '#2D5A43', 'bcg_pillar' => '#15803d', 'faculty' => '#b45309', 'course' => '#7c3aed', 'document' => '#0e7490',
        ];
        $typeLabels = [
            'topic' => 'หัวข้อ / แนวคิด', 'bcg_pillar' => 'เสาหลัก BCG', 'faculty' => 'คณะ', 'course' => 'คอร์ส / ชุดวิชา', 'document' => 'เอกสาร',
        ];
    @endphp

    @if ($record)
        <x-filament::section>
            <x-slot name="heading">กราฟของการค้นหา #{{ $record->id }}</x-slot>
            <x-slot name="description">
                “{{ $record->query }}” · {{ $record->user?->name ?? 'ผู้ใช้ที่ลบบัญชีแล้ว' }} · {{ $record->created_at?->format('d/m/Y H:i') }} · สถานะ {{ $record->status }}
            </x-slot>
            <x-slot name="afterHeader">
                <x-filament::button color="gray" size="sm" wire:click="closeReplay">กลับไปดูตามหัวข้อ</x-filament::button>
            </x-slot>
            <p style="font-size: .875rem; opacity: .75;">แสดงกราฟที่ผู้ใช้เห็นจริงตอนค้นหา (บันทึกไว้ในผลการค้นหา) ไม่ได้ดึงใหม่</p>
        </x-filament::section>
    @elseif ($history)
        <x-filament::section>
            <p>ไม่พบการค้นหา #{{ $history }}</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">เลือกสิ่งที่อยากดู</x-slot>
            <x-slot name="description">เลือกหัวข้อ แหล่งข้อมูล หรือชนิดความสัมพันธ์ ไม่ต้องเขียน query เอง · หรือกด “ดูกราฟ” ในหน้า Search History เพื่อดูกราฟของการค้นหานั้น</x-slot>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 1rem;">
                <label>
                    <span style="font-size: .875rem; font-weight: 500;">หัวข้อ</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="topic">
                            <option value="">ทุกหัวข้อ</option>
                            @foreach ($topics as $id => $name)
                                <option value="{{ $id }}">{{ $id }}. {{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span style="font-size: .875rem; font-weight: 500;">แหล่งข้อมูลของเอกสาร</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="source">
                            <option value="">ทุกแหล่ง</option>
                            @foreach ($sources as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span style="font-size: .875rem; font-weight: 500;">ชนิดความสัมพันธ์</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="predicate">
                            <option value="">ทุกชนิด</option>
                            @foreach ($predicates as $key => $label)
                                <option value="{{ $key }}">{{ $label }} ({{ $key }})</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
        </x-filament::section>
    @endif

    <div style="display: grid; grid-template-columns: minmax(0, 1fr) 20rem; gap: 1rem; align-items: start;">
        <x-filament::section>
            <x-slot name="heading">กราฟ · {{ count($graph['nodes']) }} โหนด · {{ count($graph['edges']) }} เส้น</x-slot>
            <div style="display: flex; flex-wrap: wrap; gap: .75rem; font-size: .75rem; margin-bottom: .5rem;">
                @foreach ($typeLabels as $type => $label)
                    <span style="display: inline-flex; align-items: center; gap: .35rem;">
                        <span style="width: .65rem; height: .65rem; border-radius: 9999px; background: {{ $typeColors[$type] }};"></span>{{ $label }}
                    </span>
                @endforeach
            </div>
            @if ($graph['note'])
                <p style="font-size: .875rem; opacity: .75;">{{ $graph['note'] }}</p>
            @endif
            <div wire:key="kg-canvas-{{ md5(json_encode([$graph['nodes'], $graph['edges']])) }}">
                <div wire:ignore
                     x-data="kgCanvas(@js(['nodes' => $graph['nodes'], 'edges' => $graph['edges']]), @js($typeColors))"
                     style="height: 560px; border: 1px solid rgba(127,127,127,.25); border-radius: .75rem;"></div>
            </div>
            <p style="font-size: .75rem; opacity: .6; margin-top: .5rem;">คลิกโหนดเพื่อดูเอกสารที่เกี่ยวข้อง · ลากเพื่อจัดตำแหน่ง · เลื่อนเมาส์เพื่อซูม</p>
        </x-filament::section>

        <div style="display: grid; gap: 1rem;">
            <x-filament::section>
                <x-slot name="heading">โหนดที่เลือก</x-slot>
                @if (! $panel['node'])
                    <p style="font-size: .875rem; opacity: .7;">ยังไม่ได้เลือก</p>
                @elseif ($panel['node']['type'] === 'document')
                    <p style="font-weight: 600;">{{ $panel['node']['title'] ?? $panel['node']['label'] }}</p>
                    <p style="font-size: .75rem; opacity: .7;">{{ $sources[$panel['node']['source'] ?? ''] ?? ($panel['node']['source'] ?? '') }}</p>
                    @if (! empty($panel['node']['url']))
                        <x-filament::link :href="$panel['node']['url']" target="_blank" rel="noopener">เปิดหน้าต้นฉบับ</x-filament::link>
                    @endif
                @else
                    <p style="font-weight: 600;">{{ $panel['node']['label'] }}</p>
                    <p style="font-size: .75rem; opacity: .7; margin-bottom: .5rem;">{{ $typeLabels[$panel['node']['type']] ?? $panel['node']['type'] }} · เอกสารที่กล่าวถึง {{ count($panel['documents']) }} รายการแรก</p>
                    <ul style="display: grid; gap: .5rem; font-size: .8125rem;">
                        @forelse ($panel['documents'] as $doc)
                            <li>
                                <a href="{{ $doc['url'] }}" target="_blank" rel="noopener" style="text-decoration: underline;">{{ \Illuminate\Support\Str::limit($doc['title'], 90) }}</a>
                                <span style="opacity: .6;">· {{ $sources[$doc['source']] ?? $doc['source'] }} {{ $doc['year'] }}</span>
                            </li>
                        @empty
                            <li style="opacity: .7;">ไม่พบเอกสาร</li>
                        @endforelse
                    </ul>
                @endif
            </x-filament::section>

            @if (isset($graph['evidence']))
                <x-filament::section>
                    <x-slot name="heading">หลักฐานที่ผู้ใช้เห็น ({{ count($graph['evidence']) }})</x-slot>
                    <ul style="display: grid; gap: .5rem; font-size: .8125rem;">
                        @forelse ($graph['evidence'] as $item)
                            <li>
                                <a href="{{ $item['url'] }}" target="_blank" rel="noopener" style="text-decoration: underline;">{{ \Illuminate\Support\Str::limit($item['title'], 90) }}</a>
                                <span style="opacity: .6;">· {{ $sources[$item['source']] ?? $item['source'] }}</span>
                            </li>
                        @empty
                            <li style="opacity: .7;">ไม่มี</li>
                        @endforelse
                    </ul>
                </x-filament::section>
            @endif

            <x-filament::section>
                <x-slot name="heading">ขั้นสูง</x-slot>
                <p style="font-size: .8125rem; opacity: .75;">ต้องการเขียน Cypher เอง ใช้ Neo4j explorer เดิม (ยังไม่มีข้อมูล Non-Degree)</p>
                <x-filament::link :href="route('graph.index')">เปิด /graph</x-filament::link>
            </x-filament::section>
        </div>
    </div>

    <script src="https://unpkg.com/vis-network@9.1.9/standalone/umd/vis-network.min.js"
            integrity="sha384-yxKDWWf0wwdUj/gPeuL11czrnKFQROnLgY8ll7En9NYoXibgg3C6NK/UDHNtUgWJ"
            crossorigin="anonymous"></script>
    <script>
        window.kgCanvas ??= (graph, colors) => ({
            init() {
                if (typeof vis === 'undefined') {
                    this.$el.textContent = 'โหลดไลบรารีกราฟไม่ได้';
                    return;
                }
                const nodes = new vis.DataSet(graph.nodes.map((n) => ({
                    id: n.id,
                    label: n.label,
                    title: n.title ?? n.label,
                    color: colors[n.type] ?? '#64748b',
                    shape: n.type === 'document' ? 'square' : 'dot',
                    size: n.type === 'topic' ? 16 : 11,
                })));
                const edges = new vis.DataSet(graph.edges.map((e, i) => ({ id: i, from: e.from, to: e.to, label: e.label })));
                const network = new vis.Network(this.$el, { nodes, edges }, {
                    nodes: { font: { size: 12, color: getComputedStyle(this.$el).color } },
                    edges: { arrows: { to: { enabled: true, scaleFactor: 0.5 } }, font: { size: 9, align: 'middle', color: '#94a3b8', strokeWidth: 0 }, color: '#cbd5e1', smooth: { type: 'dynamic' } },
                    physics: { stabilization: { iterations: 150 }, barnesHut: { gravitationalConstant: -4000, springLength: 150 } },
                    interaction: { hover: true, tooltipDelay: 150 },
                });
                network.on('click', (params) => {
                    if (params.nodes.length) {
                        this.$wire.selectNode(String(params.nodes[0]));
                    }
                });
            },
        });
    </script>
</x-filament-panels::page>
