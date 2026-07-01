<template>
  <div class="records-page">
    <div class="page-header">
      <div>
        <h1>🗂️ Records</h1>
        <p>Your flexible data model — custom objects, typed fields, and relationships</p>
      </div>
      <div class="header-actions">
        <button class="btn-outline" @click="openObjectModal">+ New Object</button>
        <button class="btn-outline" :disabled="!currentObject" @click="openFieldsModal">⚙ Fields</button>
        <button class="btn-create" :disabled="!currentObject" @click="openRecordModal()">+ New Record</button>
      </div>
    </div>

    <div class="layout">
      <!-- Object switcher -->
      <aside class="objects-rail">
        <div
          v-for="obj in objects"
          :key="obj.id"
          :class="['object-pill', { active: currentObject && obj.slug === currentObject.slug }]"
          @click="selectObject(obj)"
        >
          <span class="object-emoji">{{ iconFor(obj) }}</span>
          <span class="object-name">{{ obj.name }}</span>
          <span class="object-count">{{ obj.records_count ?? '' }}</span>
        </div>
        <div v-if="objects.length === 0" class="rail-empty">No objects yet</div>
      </aside>

      <!-- Records table -->
      <section class="records-main">
        <div v-if="currentObject" class="toolbar">
          <input v-model="search" class="search" placeholder="Search…" @keyup.enter="fetchRecords" />
          <button class="btn-ghost" @click="fetchRecords">Search</button>
          <div v-if="selectAttributes.length" class="view-toggle">
            <button :class="{ active: viewMode === 'table' }" @click="setView('table')">▦ Table</button>
            <button :class="{ active: viewMode === 'board' }" @click="setView('board')">▤ Board</button>
            <select v-if="viewMode === 'board'" v-model="groupByAttr" class="group-select" @change="fetchRecords">
              <option v-for="a in selectAttributes" :key="a.id" :value="a.slug">Group by {{ a.name }}</option>
            </select>
          </div>
        </div>

        <div v-if="loading" class="loading">Loading…</div>

        <div v-else-if="!currentObject" class="empty-state">
          <span>👈</span>
          <p>Select an object to view its records</p>
        </div>

        <!-- Kanban board -->
        <div v-else-if="viewMode === 'board'" class="kanban">
          <div
            v-for="col in kanbanColumns" :key="col.key"
            class="kanban-col"
            @dragover.prevent
            @drop="onDrop(col)"
          >
            <div class="kanban-col-head">
              <span class="kc-name">{{ col.label }}</span>
              <span class="kanban-count">{{ col.records.length }}</span>
            </div>
            <div class="kanban-cards">
              <div
                v-for="rec in col.records" :key="rec.id"
                class="kanban-card"
                draggable="true"
                @dragstart="onDragStart(rec)"
                @click="openTimeline(rec)"
              >
                <div class="kc-title">{{ recordTitle(rec) }}</div>
                <div v-if="cardAttributes.length" class="kc-meta">
                  <span v-for="attr in cardAttributes" :key="attr.id">{{ attr.name }}: {{ displayValue(rec, attr) }}</span>
                </div>
              </div>
              <div v-if="col.records.length === 0" class="kanban-empty">Drop here</div>
            </div>
          </div>
        </div>

        <table v-else-if="records.length" class="records-table">
          <thead>
            <tr>
              <th v-for="attr in visibleAttributes" :key="attr.id">{{ attr.name }}</th>
              <th class="actions-col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rec in records" :key="rec.id">
              <td v-for="attr in visibleAttributes" :key="attr.id">
                {{ displayValue(rec, attr) }}
              </td>
              <td class="actions-col">
                <button class="action-timeline" title="Timeline" @click="openTimeline(rec)">🕑</button>
                <button class="action-edit" @click="openRecordModal(rec)">✎</button>
                <button class="action-delete" @click="deleteRecord(rec)">🗑</button>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="empty-state">
          <span>🗂️</span>
          <p>No {{ currentObject.name.toLowerCase() }} yet</p>
          <button class="btn-outline" @click="openRecordModal()">Create the first one</button>
        </div>

        <div v-if="viewMode === 'table' && pagination.last_page > 1" class="pagination">
          <button :disabled="pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)">Prev</button>
          <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button :disabled="pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </section>
    </div>

    <!-- Record create/edit modal -->
    <div v-if="showRecordModal" class="modal" @click.self="showRecordModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ editingRecord ? 'Edit' : 'New' }} {{ currentObject?.name }}</h2>
          <button class="modal-close" @click="showRecordModal = false">✖</button>
        </div>
        <div class="modal-body">
          <div v-for="attr in editableAttributes" :key="attr.id" class="field-group">
            <label>{{ attr.name }}<span v-if="attr.is_required" class="req">*</span></label>

            <select v-if="attr.type === 'select'" v-model="form[attr.slug]">
              <option value="">—</option>
              <option v-for="opt in (attr.config?.options || [])" :key="opt" :value="opt">{{ opt }}</option>
            </select>

            <div v-else-if="attr.type === 'multiselect'" class="checks">
              <label v-for="opt in (attr.config?.options || [])" :key="opt" class="check">
                <input type="checkbox" :value="opt" v-model="form[attr.slug]" /> {{ opt }}
              </label>
            </div>

            <label v-else-if="attr.type === 'checkbox'" class="check">
              <input type="checkbox" v-model="form[attr.slug]" /> Yes
            </label>

            <select v-else-if="attr.type === 'relationship'" v-model="form[attr.slug]">
              <option value="">—</option>
              <option v-for="opt in (relationOptions[attr.slug] || [])" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>

            <input v-else-if="attr.type === 'number' || attr.type === 'currency'" type="number" v-model="form[attr.slug]" />
            <input v-else-if="attr.type === 'date'" type="date" v-model="form[attr.slug]" />
            <input v-else-if="attr.type === 'datetime'" type="datetime-local" v-model="form[attr.slug]" />
            <input v-else-if="attr.type === 'email'" type="email" v-model="form[attr.slug]" />
            <input v-else type="text" v-model="form[attr.slug]" />
          </div>
          <p v-if="formError" class="form-error">{{ formError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="showRecordModal = false">Cancel</button>
          <button class="btn-save" @click="saveRecord">{{ editingRecord ? 'Save' : 'Create' }}</button>
        </div>
      </div>
    </div>

    <!-- New object modal -->
    <div v-if="showObjectModal" class="modal" @click.self="showObjectModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>New Object</h2>
          <button class="modal-close" @click="showObjectModal = false">✖</button>
        </div>
        <div class="modal-body">
          <div class="field-group">
            <label>Name</label>
            <input v-model="objectForm.name" placeholder="e.g. Investors, Tickets, Projects" />
          </div>
          <div class="field-group">
            <label>Icon (emoji)</label>
            <input v-model="objectForm.icon" placeholder="🏷️" />
          </div>
          <p class="hint">After creating, add fields via the API or a future field editor. A default "Name" title field is recommended.</p>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="showObjectModal = false">Cancel</button>
          <button class="btn-save" @click="saveObject">Create</button>
        </div>
      </div>
    </div>

    <!-- Manage fields modal -->
    <div v-if="showFieldsModal" class="modal" @click.self="showFieldsModal = false">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Fields · {{ currentObject?.name }}</h2>
          <button class="modal-close" @click="showFieldsModal = false">✖</button>
        </div>
        <div class="modal-body">
          <div class="fields-list">
            <div v-for="attr in attributes" :key="attr.id" class="field-row">
              <div>
                <strong>{{ attr.name }}</strong>
                <span class="field-type">{{ attr.type }}</span>
                <span v-if="attr.type === 'ai'" class="field-ai">✨ AI</span>
              </div>
              <button class="action-delete" @click="deleteAttribute(attr)">🗑</button>
            </div>
          </div>

          <h3 class="add-title">Add field</h3>
          <div class="field-group">
            <label>Name</label>
            <input v-model="attrForm.name" placeholder="e.g. Lead score, Summary" />
          </div>
          <div class="field-group">
            <label>Type</label>
            <select v-model="attrForm.type">
              <option value="text">Text</option>
              <option value="number">Number</option>
              <option value="currency">Currency</option>
              <option value="date">Date</option>
              <option value="datetime">Date &amp; time</option>
              <option value="checkbox">Checkbox</option>
              <option value="email">Email</option>
              <option value="url">URL</option>
              <option value="select">Select</option>
              <option value="multiselect">Multi-select</option>
              <option value="relationship">Relationship</option>
              <option value="ai">✨ AI (computed)</option>
            </select>
          </div>

          <div v-if="['select','multiselect'].includes(attrForm.type)" class="field-group">
            <label>Options <span class="hint">(comma-separated)</span></label>
            <input v-model="attrForm.optionsText" placeholder="Low, Medium, High" />
          </div>

          <div v-if="attrForm.type === 'relationship'" class="field-group">
            <label>Target object</label>
            <select v-model="attrForm.targetObject">
              <option value="">—</option>
              <option v-for="o in objects" :key="o.id" :value="o.slug">{{ o.name }}</option>
            </select>
          </div>

          <div v-if="attrForm.type === 'ai'" class="field-group">
            <label>AI prompt <span class="hint">{{ aiPromptHint }}</span></label>
            <textarea v-model="attrForm.prompt" rows="3" :placeholder="aiPromptPlaceholder"></textarea>
            <p class="hint">Available fields: <code v-for="a in nonAiAttributes" :key="a.id">{{ braceField(a.slug) }}</code></p>
          </div>

          <div class="field-inline">
            <label class="check"><input type="checkbox" v-model="attrForm.is_required" /> Required</label>
            <label class="check"><input type="checkbox" v-model="attrForm.is_title" /> Title field</label>
          </div>
          <p v-if="attrError" class="form-error">{{ attrError }}</p>
        </div>
        <div class="modal-footer">
          <button class="btn-cancel" @click="showFieldsModal = false">Close</button>
          <button class="btn-save" @click="saveAttribute">Add field</button>
        </div>
      </div>
    </div>

    <!-- Timeline drawer -->
    <div v-if="showTimeline" class="drawer-overlay" @click.self="showTimeline = false">
      <div class="drawer">
        <div class="drawer-header">
          <div>
            <h2>🕑 Timeline</h2>
            <p class="drawer-sub">{{ recordTitle(timelineRecord) }}</p>
          </div>
          <button class="modal-close" @click="showTimeline = false">✖</button>
        </div>

        <div class="composer">
          <div class="composer-types">
            <button
              v-for="t in activityTypes" :key="t.type"
              :class="['ctype', { active: composer.type === t.type }]"
              @click="composer.type = t.type"
            >{{ t.icon }} {{ t.label }}</button>
          </div>
          <input v-model="composer.title" class="composer-title" :placeholder="composerTitlePlaceholder" />
          <textarea v-model="composer.body" class="composer-body" rows="2" placeholder="Add details…"></textarea>
          <button class="btn-save composer-add" @click="addActivity">Add to timeline</button>
        </div>

        <div class="timeline">
          <div v-if="activitiesLoading" class="loading">Loading…</div>
          <div v-else-if="activities.length === 0" class="timeline-empty">No activity yet.</div>
          <div v-for="a in activities" :key="a.id" :class="['tl-item', { system: a.is_system }]">
            <div class="tl-icon">{{ a.icon || '🗒️' }}</div>
            <div class="tl-content">
              <div class="tl-top">
                <span class="tl-title">{{ a.title }}</span>
                <span class="tl-time">{{ relTime(a.occurred_at || a.created_at) }}</span>
              </div>
              <p v-if="a.body" class="tl-body">{{ a.body }}</p>
              <div class="tl-meta">
                <span class="tl-author">{{ a.author_name }}</span>
                <button v-if="!a.is_system" class="tl-del" @click="deleteActivity(a)">delete</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '../services/api'

export default {
  data() {
    return {
      objects: [],
      currentObject: null,
      attributes: [],
      records: [],
      pagination: { current_page: 1, last_page: 1, total: 0 },
      loading: false,
      search: '',
      showRecordModal: false,
      editingRecord: null,
      form: {},
      formError: '',
      relationOptions: {},
      showObjectModal: false,
      objectForm: { name: '', icon: '' },
      showFieldsModal: false,
      attrForm: this.emptyAttrForm(),
      attrError: '',
      aiPromptHint: 'use {{field_slug}} to reference other fields',
      aiPromptPlaceholder: 'Write a one-sentence summary of {{name}} at company {{company}}.',
      showTimeline: false,
      timelineRecord: null,
      activities: [],
      activitiesLoading: false,
      composer: { type: 'note', title: '', body: '' },
      activityTypes: [
        { type: 'note', icon: '📝', label: 'Note' },
        { type: 'call', icon: '📞', label: 'Call' },
        { type: 'email', icon: '📧', label: 'Email' },
        { type: 'meeting', icon: '📅', label: 'Meeting' },
        { type: 'task', icon: '✅', label: 'Task' }
      ],
      viewMode: 'table',
      groupByAttr: '',
      draggingRecord: null
    }
  },
  computed: {
    composerTitlePlaceholder() {
      const t = this.activityTypes.find(x => x.type === this.composer.type)
      return t ? `${t.label} title (optional)` : 'Title (optional)'
    },
    selectAttributes() {
      return this.attributes.filter(a => a.type === 'select')
    },
    cardAttributes() {
      return this.attributes
        .filter(a => !a.is_title && a.slug !== this.groupByAttr && a.type !== 'ai' && a.type !== 'relationship')
        .slice(0, 2)
    },
    kanbanColumns() {
      const attr = this.attributes.find(a => a.slug === this.groupByAttr)
      const options = attr?.config?.options || []
      const cols = options.map(o => ({ key: o, label: o, value: o, records: [] }))
      cols.push({ key: '__none', label: 'Unassigned', value: '', records: [] })
      for (const rec of this.records) {
        const v = rec.data ? (rec.data[this.groupByAttr] ?? '') : ''
        const col = cols.find(c => c.value === v) || cols[cols.length - 1]
        col.records.push(rec)
      }
      return cols
    },
    visibleAttributes() {
      return this.attributes.slice(0, 6)
    },
    editableAttributes() {
      return this.attributes.filter(a => a.type !== 'ai')
    },
    nonAiAttributes() {
      return this.attributes.filter(a => a.type !== 'ai')
    }
  },
  async mounted() {
    await this.fetchObjects()
  },
  methods: {
    emptyAttrForm() {
      return { name: '', type: 'text', optionsText: '', targetObject: '', prompt: '', is_required: false, is_title: false }
    },
    braceField(slug) {
      return '{{' + slug + '}}'
    },
    openFieldsModal() {
      this.attrForm = this.emptyAttrForm()
      this.attrError = ''
      this.showFieldsModal = true
    },
    async saveAttribute() {
      this.attrError = ''
      if (!this.attrForm.name) { this.attrError = 'Name is required.'; return }
      const config = {}
      if (['select', 'multiselect'].includes(this.attrForm.type)) {
        config.options = this.attrForm.optionsText.split(',').map(s => s.trim()).filter(Boolean)
        if (!config.options.length) { this.attrError = 'Provide at least one option.'; return }
      }
      if (this.attrForm.type === 'relationship') {
        if (!this.attrForm.targetObject) { this.attrError = 'Choose a target object.'; return }
        config.target_object = this.attrForm.targetObject
      }
      if (this.attrForm.type === 'ai') {
        if (!this.attrForm.prompt.trim()) { this.attrError = 'An AI field needs a prompt.'; return }
        config.prompt = this.attrForm.prompt.trim()
      }
      try {
        await api.post(`/objects/${this.currentObject.slug}/attributes`, {
          name: this.attrForm.name,
          type: this.attrForm.type,
          config,
          is_required: this.attrForm.is_required,
          is_title: this.attrForm.is_title
        })
        // Reload attributes for the current object.
        const res = await api.get(`/objects/${this.currentObject.slug}`)
        this.attributes = res.data.attributes || []
        this.attrForm = this.emptyAttrForm()
      } catch (e) {
        this.attrError = e.response?.data?.message || 'Error adding field.'
      }
    },
    async deleteAttribute(attr) {
      if (!confirm(`Delete field "${attr.name}"? Existing data in this field is kept but hidden.`)) return
      try {
        await api.delete(`/attributes/${attr.id}`)
        this.attributes = this.attributes.filter(a => a.id !== attr.id)
      } catch (e) { alert('Error deleting field') }
    },
    iconFor(obj) {
      const map = { building: '🏢', user: '👤', target: '🎯' }
      return map[obj.icon] || obj.icon || '🗂️'
    },
    async fetchObjects() {
      try {
        const res = await api.get('/objects')
        this.objects = res.data || []
        if (this.objects.length && !this.currentObject) {
          await this.selectObject(this.objects[0])
        }
      } catch (e) { console.error(e) }
    },
    async selectObject(obj) {
      this.currentObject = obj
      this.pagination.current_page = 1
      this.viewMode = 'table'
      const res = await api.get(`/objects/${obj.slug}`)
      this.attributes = res.data.attributes || []
      this.groupByAttr = this.selectAttributes[0]?.slug || ''
      await this.fetchRecords()
    },
    setView(mode) {
      this.viewMode = mode
      if (mode === 'board' && !this.groupByAttr) {
        this.groupByAttr = this.selectAttributes[0]?.slug || ''
      }
      this.fetchRecords()
    },
    async fetchRecords() {
      if (!this.currentObject) return
      this.loading = true
      try {
        // Board mode needs all records grouped client-side, so pull a larger page.
        const params = this.viewMode === 'board'
          ? { per_page: 200, page: 1 }
          : { per_page: 25, page: this.pagination.current_page }
        if (this.search) params.q = this.search
        const res = await api.get(`/objects/${this.currentObject.slug}/records`, { params })
        this.records = res.data.data || []
        this.pagination = {
          current_page: res.data.current_page,
          last_page: res.data.last_page,
          total: res.data.total
        }
      } catch (e) { console.error(e) } finally { this.loading = false }
    },
    changePage(p) {
      this.pagination.current_page = p
      this.fetchRecords()
    },
    displayValue(rec, attr) {
      const v = rec.data ? rec.data[attr.slug] : null
      if (v === null || v === undefined || v === '') {
        return attr.type === 'ai' ? '✨ pending…' : '—'
      }
      if (attr.type === 'ai') return `✨ ${v}`
      if (Array.isArray(v)) return v.join(', ')
      if (attr.type === 'checkbox') return v ? '✓' : '—'
      if (attr.type === 'relationship') {
        const opt = (this.relationOptions[attr.slug] || []).find(o => o.id === v)
        return opt ? opt.label : `#${v}`
      }
      return v
    },
    async loadRelationOptions() {
      this.relationOptions = {}
      const rels = this.attributes.filter(a => a.type === 'relationship' && a.config?.target_object)
      for (const attr of rels) {
        try {
          const res = await api.get(`/objects/${attr.config.target_object}/records`, { params: { per_page: 200 } })
          const rows = res.data.data || []
          this.relationOptions[attr.slug] = rows.map(r => ({
            id: r.id,
            label: this.firstTextValue(r) || `#${r.id}`
          }))
        } catch (e) { this.relationOptions[attr.slug] = [] }
      }
    },
    firstTextValue(rec) {
      if (!rec.data) return null
      return rec.data.name || Object.values(rec.data).find(v => typeof v === 'string')
    },
    async openRecordModal(rec = null) {
      this.formError = ''
      this.editingRecord = rec
      const data = {}
      for (const attr of this.editableAttributes) {
        const existing = rec?.data ? rec.data[attr.slug] : undefined
        data[attr.slug] = attr.type === 'multiselect' ? (existing || []) : (existing ?? (attr.type === 'checkbox' ? false : ''))
      }
      this.form = data
      await this.loadRelationOptions()
      this.showRecordModal = true
    },
    cleanPayload() {
      const out = {}
      for (const attr of this.editableAttributes) {
        let v = this.form[attr.slug]
        if (v === '' || v === null || (Array.isArray(v) && v.length === 0)) continue
        out[attr.slug] = v
      }
      return out
    },
    async saveRecord() {
      this.formError = ''
      try {
        const payload = { data: this.cleanPayload() }
        if (this.editingRecord) {
          await api.put(`/records/${this.editingRecord.id}`, payload)
        } else {
          await api.post(`/objects/${this.currentObject.slug}/records`, payload)
        }
        this.showRecordModal = false
        await this.fetchRecords()
        await this.refreshCounts()
      } catch (e) {
        const errors = e.response?.data?.errors
        this.formError = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || 'Error saving record')
      }
    },
    async deleteRecord(rec) {
      if (!confirm('Delete this record?')) return
      await api.delete(`/records/${rec.id}`)
      await this.fetchRecords()
      await this.refreshCounts()
    },
    async refreshCounts() {
      try {
        const res = await api.get('/objects')
        this.objects = res.data || []
      } catch (e) { /* ignore */ }
    },
    openObjectModal() {
      this.objectForm = { name: '', icon: '' }
      this.showObjectModal = true
    },
    async saveObject() {
      if (!this.objectForm.name) return
      try {
        await api.post('/objects', { name: this.objectForm.name, icon: this.objectForm.icon })
        this.showObjectModal = false
        await this.fetchObjects()
      } catch (e) { alert('Error creating object') }
    },
    recordTitle(rec) {
      if (!rec) return ''
      return (rec.data && (rec.data.name || Object.values(rec.data).find(v => typeof v === 'string'))) || `#${rec.id}`
    },
    async openTimeline(rec) {
      this.timelineRecord = rec
      this.composer = { type: 'note', title: '', body: '' }
      this.showTimeline = true
      await this.fetchActivities()
    },
    async fetchActivities() {
      this.activitiesLoading = true
      try {
        const res = await api.get(`/records/${this.timelineRecord.id}/activities`)
        this.activities = res.data || []
      } catch (e) { console.error(e) } finally { this.activitiesLoading = false }
    },
    async addActivity() {
      if (!this.composer.body && !this.composer.title) return
      try {
        await api.post(`/records/${this.timelineRecord.id}/activities`, this.composer)
        this.composer = { type: this.composer.type, title: '', body: '' }
        await this.fetchActivities()
      } catch (e) { alert('Error adding activity') }
    },
    async deleteActivity(a) {
      if (a.is_system) return
      if (!confirm('Delete this entry?')) return
      try {
        await api.delete(`/activities/${a.id}`)
        await this.fetchActivities()
      } catch (e) { alert('Error deleting') }
    },
    relTime(t) {
      if (!t) return ''
      const d = new Date(t), now = new Date(), s = Math.floor((now - d) / 1000)
      if (s < 60) return 'just now'
      if (s < 3600) return `${Math.floor(s / 60)}m ago`
      if (s < 86400) return `${Math.floor(s / 3600)}h ago`
      if (s < 604800) return `${Math.floor(s / 86400)}d ago`
      return d.toLocaleDateString()
    },
    onDragStart(rec) { this.draggingRecord = rec },
    async onDrop(col) {
      const rec = this.draggingRecord
      this.draggingRecord = null
      if (!rec || col.value === '') return // can't unset a select value by drag
      if ((rec.data?.[this.groupByAttr] ?? '') === col.value) return
      // Optimistic move, then persist.
      rec.data = { ...(rec.data || {}), [this.groupByAttr]: col.value }
      try {
        await api.put(`/records/${rec.id}`, { data: { [this.groupByAttr]: col.value } })
      } catch (e) {
        alert('Error moving card')
        await this.fetchRecords()
      }
    }
  }
}
</script>

<style scoped>
.records-page { padding: 2rem; max-width: 1500px; margin: 0 auto; background: linear-gradient(135deg, #f5f7fa 0%, #eef2f6 100%); min-height: 100vh; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
.page-header h1 { font-size: 2rem; background: linear-gradient(135deg, #1e293b, #3b82f6); -webkit-background-clip: text; background-clip: text; color: transparent; }
.page-header p { color: #64748b; font-size: 0.9rem; }
.header-actions { display: flex; gap: 0.75rem; }
.btn-create { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 12px; cursor: pointer; font-weight: 600; }
.btn-create:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-outline { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 0.55rem 1rem; border-radius: 12px; cursor: pointer; font-weight: 500; }
.layout { display: grid; grid-template-columns: 240px 1fr; gap: 1.5rem; align-items: start; }
.objects-rail { background: white; border-radius: 20px; padding: 0.75rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 0.25rem; }
.object-pill { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0.75rem; border-radius: 12px; cursor: pointer; transition: background 0.15s; }
.object-pill:hover { background: #f1f5f9; }
.object-pill.active { background: #e0edff; }
.object-emoji { font-size: 1.2rem; }
.object-name { flex: 1; font-weight: 500; font-size: 0.9rem; color: #1e293b; }
.object-count { font-size: 0.7rem; color: #94a3b8; background: #f1f5f9; padding: 0.1rem 0.45rem; border-radius: 10px; }
.rail-empty { padding: 1rem; color: #94a3b8; font-size: 0.85rem; text-align: center; }
.records-main { background: white; border-radius: 20px; padding: 1.25rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); min-height: 300px; }
.toolbar { display: flex; gap: 0.5rem; margin-bottom: 1rem; align-items: center; flex-wrap: wrap; }
.search { flex: 1; min-width: 160px; padding: 0.55rem 1rem; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; }
.btn-ghost { background: #f1f5f9; border: none; padding: 0.55rem 1rem; border-radius: 12px; cursor: pointer; }
.view-toggle { display: flex; gap: 0.3rem; align-items: center; margin-left: auto; }
.view-toggle button { background: #f1f5f9; border: none; padding: 0.45rem 0.8rem; border-radius: 10px; cursor: pointer; font-size: 0.8rem; color: #475569; }
.view-toggle button.active { background: #2563eb; color: white; }
.group-select { padding: 0.45rem 0.7rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.8rem; }
/* Kanban */
.kanban { display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem; align-items: flex-start; }
.kanban-col { background: #f8fafc; border-radius: 16px; min-width: 240px; max-width: 280px; flex: 1; padding: 0.6rem; }
.kanban-col-head { display: flex; justify-content: space-between; align-items: center; padding: 0.3rem 0.5rem 0.6rem; }
.kc-name { font-weight: 600; font-size: 0.82rem; color: #334155; }
.kanban-count { font-size: 0.7rem; background: #e2e8f0; color: #475569; border-radius: 20px; padding: 0.1rem 0.5rem; }
.kanban-cards { display: flex; flex-direction: column; gap: 0.5rem; min-height: 40px; }
.kanban-card { background: white; border-radius: 12px; padding: 0.65rem 0.75rem; box-shadow: 0 1px 4px rgba(0,0,0,0.06); cursor: grab; border: 1px solid transparent; transition: border 0.15s; }
.kanban-card:hover { border-color: #c7d2fe; }
.kanban-card:active { cursor: grabbing; }
.kc-title { font-weight: 600; font-size: 0.84rem; color: #1e293b; }
.kc-meta { display: flex; flex-direction: column; gap: 0.1rem; margin-top: 0.3rem; }
.kc-meta span { font-size: 0.7rem; color: #94a3b8; }
.kanban-empty { font-size: 0.7rem; color: #cbd5e1; text-align: center; padding: 0.8rem; border: 1px dashed #e2e8f0; border-radius: 10px; }
.records-table { width: 100%; border-collapse: collapse; }
.records-table th { text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #64748b; padding: 0.6rem; border-bottom: 2px solid #f1f5f9; }
.records-table td { padding: 0.65rem 0.6rem; border-bottom: 1px solid #f8fafc; font-size: 0.85rem; color: #1e293b; }
.records-table tr:hover td { background: #f8fafc; }
.actions-col { text-align: right; white-space: nowrap; }
.action-edit, .action-delete, .action-timeline { border: none; background: #f1f5f9; border-radius: 8px; padding: 0.3rem 0.5rem; cursor: pointer; margin-left: 0.25rem; }
.action-delete { background: #fee2e2; }
.action-timeline { background: #eef2ff; }
.loading { padding: 2rem; text-align: center; color: #64748b; }
.empty-state { text-align: center; padding: 3rem; color: #64748b; }
.empty-state span { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; }
.pagination { display: flex; gap: 1rem; align-items: center; justify-content: center; margin-top: 1rem; font-size: 0.85rem; }
.pagination button { background: #f1f5f9; border: none; padding: 0.4rem 0.9rem; border-radius: 10px; cursor: pointer; }
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }
.modal { position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
.modal-content { background: white; border-radius: 24px; width: 90%; max-width: 520px; max-height: 85vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem 0; }
.modal-header h2 { font-size: 1.2rem; }
.modal-close { background: none; border: none; font-size: 1.1rem; cursor: pointer; color: #94a3b8; }
.modal-body { padding: 1.25rem 1.5rem; }
.field-group { margin-bottom: 1.1rem; }
.field-group label { display: block; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.85rem; color: #334155; }
.field-group input[type=text], .field-group input[type=email], .field-group input[type=number], .field-group input[type=date], .field-group input[type=datetime-local], .field-group select { width: 100%; padding: 0.6rem 0.9rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.9rem; }
.req { color: #dc2626; margin-left: 2px; }
.checks { display: flex; flex-wrap: wrap; gap: 0.75rem; }
.check { font-weight: 400; font-size: 0.85rem; display: flex; align-items: center; gap: 0.3rem; }
.hint { font-size: 0.75rem; color: #94a3b8; }
.hint code { background: #f1f5f9; padding: 0.05rem 0.3rem; border-radius: 6px; margin: 0 0.15rem; font-size: 0.7rem; color: #475569; }
.form-error { color: #dc2626; font-size: 0.8rem; margin-top: 0.5rem; }
.field-group textarea { width: 100%; padding: 0.6rem 0.9rem; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.9rem; font-family: inherit; resize: vertical; }
.fields-list { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1.25rem; }
.field-row { display: flex; align-items: center; justify-content: space-between; padding: 0.5rem 0.75rem; background: #f8fafc; border-radius: 12px; }
.field-row strong { font-size: 0.85rem; color: #1e293b; }
.field-type { font-size: 0.7rem; color: #64748b; margin-left: 0.5rem; text-transform: uppercase; }
.field-ai { font-size: 0.7rem; color: #7c3aed; margin-left: 0.4rem; font-weight: 600; }
.add-title { font-size: 0.95rem; color: #1e293b; margin: 0.5rem 0 0.75rem; border-top: 1px solid #f1f5f9; padding-top: 1rem; }
.field-inline { display: flex; gap: 1.5rem; margin-top: 0.5rem; }
.modal-footer { display: flex; gap: 1rem; justify-content: flex-end; padding: 0 1.5rem 1.5rem; }
.btn-cancel { background: #f1f5f9; border: none; padding: 0.6rem 1.2rem; border-radius: 12px; cursor: pointer; }
.btn-save { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 12px; cursor: pointer; font-weight: 600; }
@media (max-width: 768px) { .layout { grid-template-columns: 1fr; } }

/* Timeline drawer */
.drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; justify-content: flex-end; z-index: 1100; backdrop-filter: blur(3px); }
.drawer { width: 440px; max-width: 92vw; height: 100%; background: white; display: flex; flex-direction: column; box-shadow: -8px 0 32px rgba(0,0,0,0.15); animation: slideIn 0.2s ease; }
@keyframes slideIn { from { transform: translateX(40px); opacity: 0.6; } to { transform: translateX(0); opacity: 1; } }
.drawer-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; }
.drawer-header h2 { font-size: 1.2rem; }
.drawer-sub { font-size: 0.85rem; color: #64748b; margin-top: 0.15rem; }
.composer { padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc; }
.composer-types { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 0.6rem; }
.ctype { border: 1px solid #e2e8f0; background: white; border-radius: 20px; padding: 0.3rem 0.7rem; font-size: 0.75rem; cursor: pointer; }
.ctype.active { background: #2563eb; color: white; border-color: #2563eb; }
.composer-title { width: 100%; padding: 0.5rem 0.8rem; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 0.5rem; font-size: 0.85rem; }
.composer-body { width: 100%; padding: 0.5rem 0.8rem; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.85rem; font-family: inherit; resize: vertical; }
.composer-add { margin-top: 0.6rem; width: 100%; }
.timeline { flex: 1; overflow-y: auto; padding: 1rem 1.5rem; }
.timeline-empty { text-align: center; color: #94a3b8; padding: 2rem; font-size: 0.85rem; }
.tl-item { display: flex; gap: 0.75rem; padding: 0.6rem 0; border-left: 2px solid #e2e8f0; padding-left: 0.9rem; margin-left: 0.4rem; position: relative; }
.tl-item.system { opacity: 0.85; }
.tl-icon { font-size: 1.1rem; line-height: 1.4; }
.tl-content { flex: 1; }
.tl-top { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; }
.tl-title { font-weight: 600; font-size: 0.85rem; color: #1e293b; }
.tl-item.system .tl-title { font-weight: 500; color: #475569; }
.tl-time { font-size: 0.7rem; color: #94a3b8; white-space: nowrap; }
.tl-body { font-size: 0.82rem; color: #475569; margin: 0.25rem 0; white-space: pre-wrap; }
.tl-meta { display: flex; gap: 0.6rem; align-items: center; }
.tl-author { font-size: 0.7rem; color: #94a3b8; }
.tl-del { background: none; border: none; color: #ef4444; font-size: 0.7rem; cursor: pointer; padding: 0; }
</style>
