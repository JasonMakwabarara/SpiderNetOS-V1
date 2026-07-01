<template>
  <div class="builder">
    <!-- Toolbar -->
    <div class="builder-bar">
      <button class="bar-back" @click="$router.push('/flows')">← Flows</button>
      <h1>🎨 {{ flow.name || 'Workflow Builder' }}</h1>
      <div class="bar-actions">
        <span v-if="linking" class="linking-hint">Click a target node to connect… <button class="mini" @click="cancelLink">cancel</button></span>
        <button class="bar-run" @click="run">▶ Run</button>
        <button class="bar-save" @click="save">💾 Save</button>
      </div>
    </div>

    <div class="builder-body">
      <!-- Node palette -->
      <aside class="palette">
        <h3>Add node</h3>
        <button v-for="p in palette" :key="p.type" class="palette-btn" @click="addNode(p.type)">
          <span class="pal-icon">{{ p.icon }}</span> {{ p.label }}
        </button>
        <p class="palette-hint">Tip: use <code>&#123;&#123;field&#125;&#125;</code> in node settings to reference context values (record data, trigger payload, AI outputs).</p>
      </aside>

      <!-- Canvas -->
      <div class="canvas-wrap">
        <div class="canvas" ref="canvas">
          <svg class="edges" width="1600" height="1000">
            <defs>
              <marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                <path d="M 0 0 L 10 5 L 0 10 z" fill="#94a3b8" />
              </marker>
            </defs>
            <g v-for="(e, i) in edges" :key="'e'+i" @click="selectEdge(i)" class="edge-group">
              <line :x1="edgeGeom(e).x1" :y1="edgeGeom(e).y1" :x2="edgeGeom(e).x2" :y2="edgeGeom(e).y2"
                    :class="['edge-line', { sel: selectedEdge === i }]" marker-end="url(#arrow)" />
              <text v-if="e.branch" :x="edgeGeom(e).mx" :y="edgeGeom(e).my" class="edge-label">{{ e.branch }}</text>
            </g>
          </svg>

          <div
            v-for="node in nodes" :key="node.id"
            :class="['node', 'node-'+node.type, { sel: selectedNode === node.id, linksrc: linking === node.id }]"
            :style="{ left: node.x + 'px', top: node.y + 'px' }"
            @pointerdown="onPointerDown(node, $event)"
          >
            <div class="node-head">
              <span class="node-icon">{{ iconFor(node.type) }}</span>
              <span class="node-title">{{ labelFor(node.type) }}</span>
            </div>
            <div class="node-sub">{{ nodeSummary(node) }}</div>
            <div class="node-tools">
              <button class="node-link" title="Connect from here" @pointerdown.stop @click.stop="startLink(node)">⚲</button>
              <button class="node-del" title="Delete" @pointerdown.stop @click.stop="deleteNode(node.id)">🗑</button>
            </div>
          </div>

          <div v-if="nodes.length === 0" class="canvas-empty">Add nodes from the palette, then connect them with the ⚲ handle.</div>
        </div>
      </div>

      <!-- Inspector -->
      <aside class="inspector">
        <template v-if="selectedNodeObj">
          <h3>{{ labelFor(selectedNodeObj.type) }} settings</h3>

          <template v-if="selectedNodeObj.type === 'trigger'">
            <p class="insp-hint">Entry point. The trigger payload (record data, webhook body…) seeds the run context.</p>
          </template>

          <template v-else-if="selectedNodeObj.type === 'ai'">
            <label>Prompt</label>
            <textarea v-model="selectedNodeObj.config.prompt" rows="4" placeholder="Summarise {{name}} in one line"></textarea>
            <label>System (optional)</label>
            <input v-model="selectedNodeObj.config.system" placeholder="You are an automation step." />
            <label>Output key</label>
            <input v-model="selectedNodeObj.config.output" placeholder="ai_output" />
          </template>

          <template v-else-if="selectedNodeObj.type === 'email'">
            <label>To</label><input v-model="selectedNodeObj.config.to" placeholder="{{email}}" />
            <label>Subject</label><input v-model="selectedNodeObj.config.subject" />
            <label>Body</label><textarea v-model="selectedNodeObj.config.body" rows="3"></textarea>
          </template>

          <template v-else-if="selectedNodeObj.type === 'slack'">
            <label>Webhook URL</label><input v-model="selectedNodeObj.config.webhook" />
            <label>Message</label><textarea v-model="selectedNodeObj.config.message" rows="3"></textarea>
          </template>

          <template v-else-if="selectedNodeObj.type === 'webhook'">
            <label>URL</label><input v-model="selectedNodeObj.config.url" placeholder="https://…" />
            <label>JSON body (optional)</label>
            <textarea v-model="selectedNodeObj.config._dataText" rows="4" placeholder='{ "key": "{{value}}" }'></textarea>
          </template>

          <template v-else-if="selectedNodeObj.type === 'create_record'">
            <label>Object</label>
            <select v-model="selectedNodeObj.config.object">
              <option value="">—</option>
              <option v-for="o in objects" :key="o.id" :value="o.slug">{{ o.name }}</option>
            </select>
            <label>Field data (JSON)</label>
            <textarea v-model="selectedNodeObj.config._dataText" rows="4" placeholder='{ "name": "{{name}}" }'></textarea>
          </template>

          <template v-else-if="selectedNodeObj.type === 'condition'">
            <label>Left</label><input v-model="selectedNodeObj.config.left" placeholder="{{score}}" />
            <label>Operator</label>
            <select v-model="selectedNodeObj.config.op">
              <option value="==">==</option><option value="!=">!=</option>
              <option value=">">&gt;</option><option value=">=">&gt;=</option>
              <option value="<">&lt;</option><option value="<=">&lt;=</option>
              <option value="contains">contains</option>
              <option value="not_empty">not empty</option><option value="empty">empty</option>
            </select>
            <label>Right</label><input v-model="selectedNodeObj.config.right" placeholder="50" />
            <p class="insp-hint">Outgoing edges can be labelled <code>true</code>/<code>false</code> — click an edge to set its branch.</p>
          </template>

          <template v-else-if="selectedNodeObj.type === 'log'">
            <label>Message</label><textarea v-model="selectedNodeObj.config.message" rows="3"></textarea>
          </template>
        </template>

        <template v-else-if="selectedEdge !== null && edges[selectedEdge]">
          <h3>Edge</h3>
          <p class="insp-hint">{{ labelFor(nodeById(edges[selectedEdge].from)?.type) }} → {{ labelFor(nodeById(edges[selectedEdge].to)?.type) }}</p>
          <label>Branch (for condition source)</label>
          <select v-model="edges[selectedEdge].branch">
            <option :value="null">always</option>
            <option value="true">true</option>
            <option value="false">false</option>
          </select>
          <button class="del-edge" @click="deleteEdge(selectedEdge)">Delete edge</button>
        </template>

        <template v-else>
          <p class="insp-empty">Select a node or edge to edit its settings.</p>
        </template>
      </aside>
    </div>
  </div>
</template>

<script>
import api from '../services/api'

const NODE_W = 170
const NODE_H = 74

export default {
  data() {
    return {
      flow: {},
      nodes: [],
      edges: [],
      objects: [],
      selectedNode: null,
      selectedEdge: null,
      linking: null,
      drag: null,
      palette: [
        { type: 'trigger', icon: '🎯', label: 'Trigger' },
        { type: 'condition', icon: '🔀', label: 'Condition' },
        { type: 'ai', icon: '✨', label: 'AI Step' },
        { type: 'email', icon: '📧', label: 'Email' },
        { type: 'slack', icon: '💬', label: 'Slack' },
        { type: 'webhook', icon: '🌐', label: 'Webhook' },
        { type: 'create_record', icon: '🗂️', label: 'Create Record' },
        { type: 'log', icon: '📝', label: 'Log' }
      ]
    }
  },
  computed: {
    selectedNodeObj() { return this.nodes.find(n => n.id === this.selectedNode) || null }
  },
  async mounted() {
    await this.load()
    try { const res = await api.get('/objects'); this.objects = res.data || [] } catch(e) {}
  },
  methods: {
    async load() {
      try {
        const res = await api.get(`/flows/${this.$route.params.id}`)
        this.flow = res.data
        const g = res.data.graph || {}
        this.nodes = (g.nodes || []).map(n => ({ ...n, config: this.hydrate(n) }))
        this.edges = (g.edges || []).map(e => ({ branch: null, ...e }))
      } catch(e) { alert('Could not load flow'); this.$router.push('/flows') }
    },
    hydrate(node) {
      const c = { ...(node.config || {}) }
      // JSON object fields are edited as text in the inspector.
      if (['webhook', 'create_record'].includes(node.type) && c.data && !c._dataText) {
        c._dataText = JSON.stringify(c.data, null, 2)
      }
      return c
    },
    iconFor(t) { return (this.palette.find(p => p.type === t) || {}).icon || '⚙️' },
    labelFor(t) { return (this.palette.find(p => p.type === t) || {}).label || t },
    nodeSummary(node) {
      const c = node.config || {}
      switch (node.type) {
        case 'condition': return `${c.left || '?'} ${c.op || '=='} ${c.right || '?'}`
        case 'ai': return c.prompt ? c.prompt.slice(0, 28) + '…' : 'no prompt'
        case 'email': return c.to || 'no recipient'
        case 'create_record': return c.object || 'no object'
        case 'webhook': return c.url || 'no url'
        case 'slack': return 'slack message'
        case 'log': return (c.message || '').slice(0, 28)
        default: return ''
      }
    },
    addNode(type) {
      const id = 'n' + Date.now().toString(36)
      const offset = this.nodes.length * 24
      this.nodes.push({ id, type, x: 60 + offset % 400, y: 60 + offset % 300, config: type === 'ai' ? { output: 'ai_output' } : (type === 'condition' ? { op: '>' } : {}) })
      this.selectedNode = id; this.selectedEdge = null
    },
    deleteNode(id) {
      this.nodes = this.nodes.filter(n => n.id !== id)
      this.edges = this.edges.filter(e => e.from !== id && e.to !== id)
      if (this.selectedNode === id) this.selectedNode = null
    },
    startLink(node) { this.linking = node.id },
    cancelLink() { this.linking = null },
    nodeById(id) { return this.nodes.find(n => n.id === id) },
    selectEdge(i) { this.selectedEdge = i; this.selectedNode = null },
    deleteEdge(i) { this.edges.splice(i, 1); this.selectedEdge = null },
    edgeGeom(e) {
      const a = this.nodeById(e.from), b = this.nodeById(e.to)
      if (!a || !b) return { x1: 0, y1: 0, x2: 0, y2: 0, mx: 0, my: 0 }
      const x1 = a.x + NODE_W / 2, y1 = a.y + NODE_H / 2
      const x2 = b.x + NODE_W / 2, y2 = b.y + NODE_H / 2
      return { x1, y1, x2, y2, mx: (x1 + x2) / 2, my: (y1 + y2) / 2 - 6 }
    },
    onPointerDown(node, e) {
      const rect = this.$refs.canvas.getBoundingClientRect()
      this.drag = { id: node.id, offX: e.clientX - rect.left - node.x, offY: e.clientY - rect.top - node.y, moved: false }
      window.addEventListener('pointermove', this.onPointerMove)
      window.addEventListener('pointerup', this.onPointerUp)
    },
    onPointerMove(e) {
      if (!this.drag) return
      const node = this.nodeById(this.drag.id)
      const rect = this.$refs.canvas.getBoundingClientRect()
      const nx = Math.max(0, e.clientX - rect.left - this.drag.offX)
      const ny = Math.max(0, e.clientY - rect.top - this.drag.offY)
      if (Math.abs(nx - node.x) > 2 || Math.abs(ny - node.y) > 2) this.drag.moved = true
      node.x = nx; node.y = ny
    },
    onPointerUp() {
      window.removeEventListener('pointermove', this.onPointerMove)
      window.removeEventListener('pointerup', this.onPointerUp)
      if (this.drag && !this.drag.moved) {
        const id = this.drag.id
        if (this.linking && this.linking !== id) {
          if (!this.edges.some(e => e.from === this.linking && e.to === id)) {
            this.edges.push({ from: this.linking, to: id, branch: null })
          }
          this.linking = null
        } else {
          this.selectedNode = id; this.selectedEdge = null
        }
      }
      this.drag = null
    },
    serializeNodes() {
      return this.nodes.map(n => {
        const config = { ...n.config }
        if (['webhook', 'create_record'].includes(n.type)) {
          if (config._dataText && config._dataText.trim()) {
            try { config.data = JSON.parse(config._dataText) } catch(e) { /* keep previous data */ }
          }
          delete config._dataText
        }
        return { id: n.id, type: n.type, x: Math.round(n.x), y: Math.round(n.y), config }
      })
    },
    async save() {
      try {
        await api.put(`/flows/${this.flow.id}`, { graph: { nodes: this.serializeNodes(), edges: this.edges } })
        alert('Workflow saved')
        await this.load()
      } catch(e) { alert(e.response?.data?.message || 'Error saving workflow') }
    },
    async run() {
      await this.save()
      try {
        const res = await api.post(`/flows/${this.flow.id}/execute`)
        const steps = (res.data.run?.steps || []).map(s => `${s.status}: ${s.type} — ${s.message}`).join('\n')
        alert(`${res.data.message}\n\n${steps}`)
      } catch(e) { alert('Error running workflow') }
    }
  }
}
</script>

<style scoped>
.builder { height: 100vh; display: flex; flex-direction: column; background: #0f172a; color: #e2e8f0; }
.builder-bar { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 1.25rem; background: #1e293b; border-bottom: 1px solid #334155; }
.builder-bar h1 { font-size: 1.1rem; font-weight: 600; flex: 1; }
.bar-back { background: none; border: none; color: #93c5fd; cursor: pointer; font-weight: 600; }
.bar-actions { display: flex; gap: 0.6rem; align-items: center; }
.linking-hint { font-size: 0.75rem; color: #fcd34d; }
.mini { background: none; border: none; color: #93c5fd; cursor: pointer; text-decoration: underline; }
.bar-run { background: #2563eb; color: white; border: none; padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
.bar-save { background: #7c3aed; color: white; border: none; padding: 0.5rem 1rem; border-radius: 10px; cursor: pointer; font-weight: 600; }
.builder-body { flex: 1; display: grid; grid-template-columns: 200px 1fr 280px; overflow: hidden; }
.palette { background: #1e293b; padding: 1rem; border-right: 1px solid #334155; overflow-y: auto; }
.palette h3, .inspector h3 { font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.75rem; letter-spacing: 0.05em; }
.palette-btn { display: flex; align-items: center; gap: 0.5rem; width: 100%; background: #0f172a; border: 1px solid #334155; color: #e2e8f0; padding: 0.55rem 0.7rem; border-radius: 10px; cursor: pointer; margin-bottom: 0.5rem; font-size: 0.85rem; transition: all 0.15s; }
.palette-btn:hover { border-color: #6366f1; background: #1e293b; }
.pal-icon { font-size: 1.1rem; }
.palette-hint { font-size: 0.7rem; color: #64748b; margin-top: 1rem; line-height: 1.5; }
.palette-hint code { background: #0f172a; padding: 0.05rem 0.3rem; border-radius: 5px; }
.canvas-wrap { overflow: auto; background: radial-gradient(circle, #1e293b 1px, transparent 1px); background-size: 24px 24px; background-color: #0b1220; }
.canvas { position: relative; width: 1600px; height: 1000px; }
.edges { position: absolute; top: 0; left: 0; pointer-events: none; }
.edge-group { pointer-events: auto; cursor: pointer; }
.edge-line { stroke: #94a3b8; stroke-width: 2; }
.edge-line.sel { stroke: #818cf8; stroke-width: 3; }
.edge-label { fill: #fcd34d; font-size: 11px; font-weight: 700; text-anchor: middle; }
.node { position: absolute; width: 170px; min-height: 74px; background: #1e293b; border: 1px solid #334155; border-radius: 14px; padding: 0.6rem 0.7rem; cursor: grab; box-shadow: 0 4px 14px rgba(0,0,0,0.3); user-select: none; touch-action: none; }
.node.sel { border-color: #818cf8; box-shadow: 0 0 0 2px rgba(129,140,248,0.4); }
.node.linksrc { border-color: #fcd34d; }
.node-trigger { background: #134e4a; }
.node-condition { background: #422006; }
.node-ai { background: #3b0764; }
.node-head { display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.25rem; }
.node-icon { font-size: 1rem; }
.node-title { font-size: 0.82rem; font-weight: 600; }
.node-sub { font-size: 0.68rem; color: #94a3b8; word-break: break-word; }
.node-tools { position: absolute; top: 0.4rem; right: 0.5rem; display: flex; gap: 0.2rem; opacity: 0; transition: opacity 0.15s; }
.node:hover .node-tools { opacity: 1; }
.node-link, .node-del { background: rgba(255,255,255,0.08); border: none; border-radius: 6px; cursor: pointer; font-size: 0.7rem; padding: 0.1rem 0.3rem; }
.canvas-empty { position: absolute; top: 40%; left: 50%; transform: translateX(-50%); color: #475569; font-size: 0.9rem; }
.inspector { background: #1e293b; border-left: 1px solid #334155; padding: 1rem; overflow-y: auto; }
.inspector label { display: block; font-size: 0.72rem; color: #94a3b8; margin: 0.6rem 0 0.25rem; }
.inspector input, .inspector textarea, .inspector select { width: 100%; background: #0f172a; border: 1px solid #334155; color: #e2e8f0; border-radius: 8px; padding: 0.45rem 0.6rem; font-size: 0.8rem; font-family: inherit; }
.insp-hint { font-size: 0.72rem; color: #64748b; line-height: 1.5; margin-top: 0.5rem; }
.insp-hint code { background: #0f172a; padding: 0.05rem 0.3rem; border-radius: 5px; }
.insp-empty { font-size: 0.8rem; color: #64748b; }
.del-edge { margin-top: 1rem; background: #7f1d1d; color: #fecaca; border: none; padding: 0.4rem 0.8rem; border-radius: 8px; cursor: pointer; width: 100%; }
</style>
