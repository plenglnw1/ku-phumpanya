/**
 * Force-directed knowledge graph layout (no external deps).
 */

const TYPE_COLORS = {
    topic: '#2D5A43',
    faculty: '#22C55E',
    course: '#3B82F6',
    bcg_pillar: '#EAB308',
};

function truncate(text, max = 14) {
    if (!text || text.length <= max) {
        return text ?? '';
    }

    return `${text.slice(0, max - 1)}…`;
}

function buildSimulationNodes(graph) {
    const center = graph.center ?? { label: 'Topic', color: TYPE_COLORS.topic, type: 'topic' };
    const nodes = graph.nodes ?? [];
    const edges = graph.edges ?? [];

    const byLabel = new Map();

    const addNode = (label, meta = {}) => {
        if (!label || byLabel.has(label)) {
            return byLabel.get(label);
        }

        const node = {
            id: label,
            label,
            color: meta.color ?? TYPE_COLORS.topic,
            type: meta.type ?? 'topic',
            x: 0,
            y: 0,
            vx: 0,
            vy: 0,
            fixed: false,
        };

        byLabel.set(label, node);

        return node;
    };

    const centerNode = addNode(center.label, center);
    centerNode.fixed = true;
    centerNode.color = center.color ?? TYPE_COLORS.topic;
    centerNode.type = center.type ?? 'topic';

    nodes.forEach((node) => {
        addNode(node.label, node);
    });

    edges.forEach((edge) => {
        addNode(edge.from);
        addNode(edge.to);
    });

    const simNodes = Array.from(byLabel.values());
    const simEdges = edges
        .map((edge) => ({
            from: byLabel.get(edge.from),
            to: byLabel.get(edge.to),
            type: edge.type ?? 'relatedTo',
        }))
        .filter((edge) => edge.from && edge.to);

    const width = 480;
    const height = 360;
    const cx = width / 2;
    const cy = height / 2;

    simNodes.forEach((node, index) => {
        if (node.fixed) {
            node.x = cx;
            node.y = cy;

            return;
        }

        const angle = (2 * Math.PI * index) / Math.max(simNodes.length, 1);
        node.x = cx + Math.cos(angle) * 90;
        node.y = cy + Math.sin(angle) * 90;
    });

    return { simNodes, simEdges, width, height, cx, cy };
}

function tickSimulation(simNodes, simEdges, cx, cy) {
    const repulsion = 4200;
    const spring = 0.045;
    const idealLength = 95;
    const damping = 0.82;
    const centerPull = 0.02;

    for (let i = 0; i < simNodes.length; i++) {
        for (let j = i + 1; j < simNodes.length; j++) {
            const a = simNodes[i];
            const b = simNodes[j];
            let dx = a.x - b.x;
            let dy = a.y - b.y;
            let distSq = dx * dx + dy * dy + 0.01;
            const force = repulsion / distSq;
            const dist = Math.sqrt(distSq);
            dx = (dx / dist) * force;
            dy = (dy / dist) * force;

            if (!a.fixed) {
                a.vx += dx;
                a.vy += dy;
            }
            if (!b.fixed) {
                b.vx -= dx;
                b.vy -= dy;
            }
        }
    }

    simEdges.forEach((edge) => {
        const a = edge.from;
        const b = edge.to;
        let dx = b.x - a.x;
        let dy = b.y - a.y;
        const dist = Math.sqrt(dx * dx + dy * dy) || 0.01;
        const delta = dist - idealLength;
        const force = spring * delta;
        dx = (dx / dist) * force;
        dy = (dy / dist) * force;

        if (!a.fixed) {
            a.vx += dx;
            a.vy += dy;
        }
        if (!b.fixed) {
            b.vx -= dx;
            b.vy -= dy;
        }
    });

    simNodes.forEach((node) => {
        if (node.fixed) {
            node.vx = 0;
            node.vy = 0;

            return;
        }

        node.vx += (cx - node.x) * centerPull;
        node.vy += (cy - node.y) * centerPull;
        node.vx *= damping;
        node.vy *= damping;
        node.x += node.vx;
        node.y += node.vy;
    });
}

function renderGraph(svg, simNodes, simEdges, width, height) {
    while (svg.firstChild) {
        svg.removeChild(svg.firstChild);
    }

    const ns = 'http://www.w3.org/2000/svg';
    const tooltip = document.createElementNS(ns, 'title');

    simEdges.forEach((edge) => {
        const line = document.createElementNS(ns, 'line');
        line.setAttribute('x1', String(edge.from.x));
        line.setAttribute('y1', String(edge.from.y));
        line.setAttribute('x2', String(edge.to.x));
        line.setAttribute('y2', String(edge.to.y));
        line.setAttribute('stroke', '#9ca3af');
        line.setAttribute('stroke-width', '1.5');
        line.setAttribute('opacity', '0.85');

        const title = tooltip.cloneNode();
        title.textContent = `${edge.from.label} --[${edge.type}]--> ${edge.to.label}`;
        line.appendChild(title);

        svg.appendChild(line);
    });

    simNodes.forEach((node) => {
        const radius = node.fixed ? 30 : 24;
        const circle = document.createElementNS(ns, 'circle');
        circle.setAttribute('cx', String(node.x));
        circle.setAttribute('cy', String(node.y));
        circle.setAttribute('r', String(radius));
        circle.setAttribute('fill', node.color);
        circle.setAttribute('stroke', '#ffffff');
        circle.setAttribute('stroke-width', '2');

        const title = tooltip.cloneNode();
        title.textContent = `${node.label} (${node.type})`;
        circle.appendChild(title);

        const text = document.createElementNS(ns, 'text');
        text.setAttribute('x', String(node.x));
        text.setAttribute('y', String(node.y));
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('dominant-baseline', 'middle');
        text.setAttribute('fill', '#ffffff');
        text.setAttribute('font-size', node.fixed ? '9' : '7');
        text.setAttribute('font-weight', '600');
        text.textContent = truncate(node.label, node.fixed ? 16 : 12);

        svg.appendChild(circle);
        svg.appendChild(text);
    });

    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
}

function mountKnowledgeGraphs() {
    document.querySelectorAll('[data-knowledge-graph]').forEach((container) => {
        if (container.dataset.mounted === 'true') {
            return;
        }

        let graph;
        try {
            graph = JSON.parse(container.dataset.knowledgeGraph ?? '{}');
        } catch {
            return;
        }

        const svg = container.querySelector('svg');
        if (!svg) {
            return;
        }

        const { simNodes, simEdges, width, height, cx, cy } = buildSimulationNodes(graph);
        if (simNodes.length === 0) {
            return;
        }

        container.dataset.mounted = 'true';

        let frame = 0;
        const maxFrames = 90;

        const animate = () => {
            tickSimulation(simNodes, simEdges, cx, cy);
            renderGraph(svg, simNodes, simEdges, width, height);
            frame += 1;

            if (frame < maxFrames) {
                requestAnimationFrame(animate);
            }
        };

        animate();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountKnowledgeGraphs);
} else {
    mountKnowledgeGraphs();
}

export { mountKnowledgeGraphs };
