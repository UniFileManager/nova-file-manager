<template>
  <DefaultField :field="field" :errors="errors" :show-help-text="showHelpText">
    <template #field>
      <input type="hidden" :name="field.attribute" :value="encodedValue" />

      <div class="ufm-picker">
        <button
          v-if="shouldShowChooser"
          type="button"
          class="ufm-picker__dropzone"
          @click="openLibrary"
        >
          <strong>{{ field.uploadHeading ?? 'Choose from Library' }}</strong>
          <span>{{ field.uploadDescription ?? 'Select an existing file or upload a new one.' }}</span>
        </button>

        <div v-if="selectedFiles.length" class="ufm-picker__selected" :class="{ 'is-grid': field.imageCardView }">
          <article v-for="(file, index) in selectedFiles" :key="`${file.path}-${index}`" class="ufm-picker__item">
            <img v-if="file.thumbnail_url" :src="file.thumbnail_url" :alt="file.name" loading="lazy" />
            <span v-else class="ufm-picker__file-icon">{{ fileLabel(file) }}</span>
            <strong>{{ file.name }}</strong>
            <button type="button" @click="removeAt(index)">Remove</button>
          </article>
        </div>

        <button
          v-if="field.clearable !== false && selectedFiles.length > 0"
          type="button"
          class="ufm-picker__clear"
          @click="clearSelection"
        >
          Clear selection
        </button>

        <div v-if="isLibraryOpen" class="ufm-nova__modal-backdrop" @click.self="closeLibrary">
          <section class="ufm-picker__library-modal" role="dialog" aria-modal="true" aria-label="Choose files">
            <header class="ufm-nova__modal-header">
              <div>
                <p>{{ isMultiple ? 'Choose files' : 'Choose a file' }}</p>
                <h2>Select from your library</h2>
              </div>
              <button type="button" @click="closeLibrary">×</button>
            </header>

            <div class="ufm-picker__library-topbar">
              <div class="ufm-picker__path">
                <button type="button" :disabled="path === basePath" @click="goBack">‹</button>
                <strong>{{ pathTitle }}</strong>
              </div>

              <div class="ufm-picker__library-actions">
                <div v-if="availableStorageAreas.length >= 1 && !field.storageArea" class="ufm-nova__area-dropdown">
                  <button
                    type="button"
                    class="ufm-nova__area-trigger"
                    :aria-expanded="isStorageMenuOpen"
                    aria-haspopup="true"
                    @click="isStorageMenuOpen = !isStorageMenuOpen"
                  >
                    <span>{{ storageIcon(activeStorageArea) }}</span>
                    {{ activeStorageArea?.label ?? 'Storage area' }}
                    <span class="ufm-nova__area-chevron">⌄</span>
                  </button>

                  <div v-if="isStorageMenuOpen" class="ufm-nova__area-menu" role="menu">
                    <button
                      v-for="storageArea in availableStorageAreas"
                      :key="storageArea.key"
                      type="button"
                      :class="{ 'is-active': area === storageArea.key }"
                      role="menuitem"
                      @click="changeArea(storageArea.key)"
                    >
                      <span>{{ storageIcon(storageArea) }}</span>
                      <strong>{{ storageArea.label }}</strong>
                      <span v-if="area === storageArea.key" class="ufm-nova__area-selected">✓</span>
                    </button>
                  </div>
                </div>

                <button type="button" class="ufm-nova__upload-button" @click="openUploadModal">
                  Upload
                </button>
              </div>
            </div>

            <div class="ufm-picker__controls">
              <select v-model="sortBy" class="ufm-nova__select" aria-label="Sort library files">
                <option value="name">Name</option>
                <option value="modified_at">Last modified</option>
                <option value="type">Type</option>
              </select>
              <button type="button" class="ufm-nova__button is-secondary" @click="toggleSortDirection">
                {{ sortDirection === 'asc' ? 'Ascending' : 'Descending' }}
              </button>
            </div>

            <div v-if="selectedFiles.length && isMultiple" class="ufm-picker__modal-selection">
              <strong>{{ selectedFiles.length }} of {{ maxFiles }} selected</strong>
              <button type="button" @click="clearSelection">Clear selection</button>
            </div>

            <div v-if="isLoading" class="ufm-picker__empty">Loading files…</div>

            <div v-else-if="visibleItems.length === 0" class="ufm-picker__empty">
              This folder is empty.
            </div>

            <div v-else class="ufm-picker__grid">
              <button
                v-for="item in visibleItems"
                :key="item.path"
                type="button"
                class="ufm-picker__card"
                :class="{ 'is-selected': isSelected(item), 'is-disabled': !canSelect(item) }"
                @click="handleLibraryItem(item)"
              >
                <img v-if="isImage(item)" :src="item.thumbnail_url" :alt="item.name" loading="lazy" />
                <span v-else class="ufm-picker__file-icon">{{ item.type === 'directory' ? '📁' : fileLabel(item) }}</span>
                <span v-if="isSelected(item)" class="ufm-nova__check">✓</span>
                <strong>{{ item.name }}</strong>
                <span>{{ item.type === 'directory' ? 'Folder' : formatSize(item.size) }}</span>
              </button>
            </div>

            <footer v-if="pageCount > 1" class="ufm-nova__pagination">
              <button type="button" :disabled="page === 1" @click="page = 1">First</button>
              <button type="button" :disabled="page === 1" @click="page -= 1">Previous</button>
              <span>Page {{ page }} of {{ pageCount }}</span>
              <button type="button" :disabled="page === pageCount" @click="page += 1">Next</button>
              <button type="button" :disabled="page === pageCount" @click="page = pageCount">Last</button>
            </footer>
          </section>
        </div>

        <div v-if="isUploadModalOpen" class="ufm-nova__modal-backdrop" @click.self="closeUploadModal">
          <section class="ufm-nova__modal is-upload" role="dialog" aria-modal="true" aria-label="Upload files">
            <header class="ufm-nova__modal-header">
              <div>
                <p>Upload files</p>
                <h2>Add files to {{ pathTitle }}</h2>
              </div>
              <button type="button" @click="closeUploadModal">×</button>
            </header>

            <div class="ufm-nova__upload-body">
              <label class="ufm-nova__dropzone" @dragover.prevent @drop.prevent="uploadDroppedFiles">
                <input type="file" multiple :accept="acceptAttribute" @change="uploadFiles" />
                <span>⇧</span>
                <strong>Choose files or drag and drop here</strong>
                <small>
                  Uploaded files are selected automatically. You can upload up to {{ uploadLimit }} files at a time.
                </small>
              </label>

              <div v-if="isUploading" class="ufm-nova__progress">
                <span :style="{ width: `${uploadProgress}%` }"></span>
              </div>

              <section v-if="recentUploads.length > 0" class="ufm-nova__recent-uploads">
                <header>
                  <h3>Recently uploaded</h3>
                  <button type="button" @click="recentUploads = []">Clear list</button>
                </header>

                <div class="ufm-nova__upload-grid">
                  <article v-for="item in recentUploads" :key="item.path" class="ufm-nova__upload-card">
                    <img v-if="isImage(item)" :src="item.thumbnail_url" :alt="item.name" loading="lazy" />
                    <span v-else class="ufm-nova__file-icon">{{ fileLabel(item) }}</span>
                    <strong :title="item.name">{{ item.name }}</strong>
                  </article>
                </div>
              </section>
            </div>

            <footer class="ufm-nova__modal-footer">
              <button type="button" class="ufm-nova__button is-secondary" @click="closeUploadModal">Close</button>
            </footer>
          </section>
        </div>
      </div>
    </template>
  </DefaultField>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps({
  resourceName: {
    type: String,
    required: true,
  },
  resourceId: {
    type: [String, Number],
    default: null,
  },
  field: {
    type: Object,
    required: true,
  },
  errors: {
    type: Object,
    required: true,
  },
  showHelpText: {
    type: Boolean,
    default: true,
  },
})

const novaConfig = Nova.config('unifilemanager') ?? {}
const apiBase = novaConfig.apiBase ?? '/nova-vendor/unifilemanager/nova-file-manager'
const basePath = props.field.directory ?? ''
const path = ref(basePath)
const items = ref([])
const availableStorageAreas = ref(novaConfig.storageAreas ?? [])
const selectedFiles = ref([])
const isLibraryOpen = ref(false)
const isLoading = ref(false)
const page = ref(1)
const perPage = 20
const sortBy = ref(window.localStorage.getItem('ufm:nova-picker:sort-by') ?? 'name')
const sortDirection = ref(window.localStorage.getItem('ufm:nova-picker:sort-direction') ?? 'asc')
const isStorageMenuOpen = ref(false)
const isUploadModalOpen = ref(false)
const isUploading = ref(false)
const uploadProgress = ref(0)
const recentUploads = ref([])

const isMultiple = computed(() => props.field.multiple === true)
const maxFiles = computed(() => Number(props.field.maxFiles ?? (isMultiple.value ? 10 : 1)))
const area = ref(props.field.storageArea ?? novaConfig.defaultArea ?? 'private')
const allowedMimeTypes = computed(() => props.field.allowedMimeTypes ?? [])
const acceptAttribute = computed(() => allowedMimeTypes.value.join(','))
const uploadLimit = computed(() => Number(novaConfig.maxUploadFiles ?? 10))
const activeStorageArea = computed(() => availableStorageAreas.value.find((storageArea) => storageArea.key === area.value))

const encodedValue = computed(() => {
  const paths = selectedFiles.value.map((file) => file.path)

  return isMultiple.value ? JSON.stringify(paths) : (paths[0] ?? '')
})

const shouldShowChooser = computed(() => {
  if (isMultiple.value) {
    return selectedFiles.value.length < maxFiles.value
  }

  return selectedFiles.value.length === 0
})

const pathTitle = computed(() => {
  if (path.value === basePath) {
    return basePath === '' ? 'Main Library' : basePath.split('/').at(-1)
  }

  return path.value.split('/').at(-1)
})

const selectableItems = computed(() => items.value
  .filter((item) => item.type === 'directory' || canSelect(item))
  .sort((left, right) => compareItems(left, right)))
const pageCount = computed(() => Math.max(1, Math.ceil(selectableItems.value.length / perPage)))
const visibleItems = computed(() => {
  const start = (page.value - 1) * perPage

  return selectableItems.value.slice(start, start + perPage)
})

watch(path, () => {
  page.value = 1
})

watch([sortBy, sortDirection], () => {
  page.value = 1
  window.localStorage.setItem('ufm:nova-picker:sort-by', sortBy.value)
  window.localStorage.setItem('ufm:nova-picker:sort-direction', sortDirection.value)
})

watch(pageCount, (nextPageCount) => {
  if (page.value > nextPageCount) {
    page.value = nextPageCount
  }
})

onMounted(async () => {
  await loadStorageAreas()
  await hydrateInitialValue()
})

defineExpose({ fill })

function fill(formData) {
  formData.append(props.field.attribute, encodedValue.value)
}

async function openLibrary() {
  isLibraryOpen.value = true
  await loadItems()
}

function closeLibrary() {
  isLibraryOpen.value = false
  closeUploadModal()
}

async function loadStorageAreas() {
  const { data } = await Nova.request().get(`${apiBase}/storage-areas`)
  availableStorageAreas.value = data.data ?? []

  if (!availableStorageAreas.value.some((storageArea) => storageArea.key === area.value)) {
    area.value = availableStorageAreas.value.find((storageArea) => storageArea.default)?.key
      ?? availableStorageAreas.value[0]?.key
      ?? area.value
  }
}

async function hydrateInitialValue() {
  const paths = normaliseInitialValue(props.field.value)

  for (const selectedPath of paths) {
    await addSelectedPath(selectedPath)
  }
}

function normaliseInitialValue(value) {
  if (Array.isArray(value)) {
    return value
  }

  if (typeof value === 'string' && value.startsWith('[')) {
    try {
      const decoded = JSON.parse(value)

      return Array.isArray(decoded) ? decoded : []
    } catch {
      return []
    }
  }

  return value ? [value] : []
}

async function loadItems() {
  isLoading.value = true

  try {
    const { data } = await Nova.request().get(`${apiBase}/items`, {
      params: { area: area.value, path: path.value },
    })

    items.value = data.data ?? []
  } finally {
    isLoading.value = false
  }
}

async function changeArea(nextArea) {
  area.value = nextArea
  path.value = basePath
  page.value = 1
  isStorageMenuOpen.value = false
  await loadItems()
}

function storageIcon(storageArea) {
  return storageArea?.visibility === 'public' ? '▣' : '▢'
}

function goBack() {
  if (path.value === basePath) {
    return
  }

  const parent = path.value.split('/').slice(0, -1).join('/')
  path.value = parent.startsWith(basePath) ? parent : basePath
  loadItems()
}

async function handleLibraryItem(item) {
  if (item.type === 'directory') {
    path.value = item.path
    await loadItems()
    return
  }

  if (!canSelect(item)) {
    return
  }

  await addSelectedPath(item.path, item)

  if (!isMultiple.value || selectedFiles.value.length >= maxFiles.value) {
    closeLibrary()
  }
}

async function addSelectedPath(selectedPath, existingItem = null) {
  if (!isMultiple.value && selectedFiles.value.length > 0) {
    selectedFiles.value = []
  }

  if (selectedFiles.value.length >= maxFiles.value) {
    return null
  }

  if (props.field.allowDuplicateSelection !== true && selectedFiles.value.some((file) => file.path === selectedPath)) {
    return null
  }

  const item = existingItem ?? await fetchSelectableFile(selectedPath)
  const selectedFile = {
    name: item.name ?? selectedPath.split('/').at(-1),
    path: item.path ?? selectedPath,
    mime_type: item.mime_type,
    thumbnail_url: item.thumbnail_url ?? thumbnailUrl(item.path ?? selectedPath),
  }

  selectedFiles.value = [...selectedFiles.value, selectedFile]

  return selectedFile
}

async function fetchSelectableFile(selectedPath) {
  const { data } = await Nova.request().get(`${apiBase}/selectable-file`, {
    params: { area: area.value, path: selectedPath },
  })

  const file = data.data

  return {
    ...file,
    name: file.path.split('/').at(-1),
    thumbnail_url: thumbnailUrl(file.path),
  }
}

function openUploadModal() {
  isUploadModalOpen.value = true
}

function closeUploadModal() {
  isUploadModalOpen.value = false
  recentUploads.value = []
  uploadProgress.value = 0
}

async function uploadFiles(event) {
  const files = [...(event.target.files ?? [])]
  event.target.value = ''

  await uploadSelectedFiles(files)
}

async function uploadDroppedFiles(event) {
  await uploadSelectedFiles([...(event.dataTransfer?.files ?? [])])
}

async function uploadSelectedFiles(files) {
  if (files.length === 0) {
    return
  }

  if (files.length > uploadLimit.value) {
    notifyError(`Choose up to ${uploadLimit.value} files at a time.`)
    return
  }

  isUploading.value = true
  uploadProgress.value = 0

  for (const [index, file] of files.entries()) {
    const form = new FormData()
    form.append('area', area.value)
    form.append('path', path.value)
    form.append('file', file)

    try {
      const { data } = await Nova.request().post(`${apiBase}/uploads`, form)
      const uploadedFile = await addSelectedPath(data.path)

      if (uploadedFile) {
        recentUploads.value = [uploadedFile, ...recentUploads.value]
      }

      uploadProgress.value = Math.round(((index + 1) / files.length) * 100)
    } catch (exception) {
      notifyError(exception?.response?.data?.message ?? `${file.name} could not be uploaded.`)
    }
  }

  await loadItems()
  isUploading.value = false
}

function removeAt(index) {
  selectedFiles.value = selectedFiles.value.filter((_, currentIndex) => currentIndex !== index)
}

function clearSelection() {
  selectedFiles.value = []
}

function isSelected(item) {
  return selectedFiles.value.some((file) => file.path === item.path)
}

function canSelect(item) {
  if (item.type === 'directory') {
    return true
  }

  if (allowedMimeTypes.value.length === 0) {
    return true
  }

  return allowedMimeTypes.value.some((mimeType) => {
    if (mimeType.endsWith('/*')) {
      return String(item.mime_type ?? '').startsWith(mimeType.slice(0, -1))
    }

    return item.mime_type === mimeType
  })
}

function isImage(item) {
  return item.type === 'file' && String(item.mime_type ?? '').startsWith('image/')
}

function fileLabel(item) {
  const extension = String(item.name ?? '').split('.').pop()?.toUpperCase()

  return extension && extension.length <= 5 ? extension : 'FILE'
}

function toggleSortDirection() {
  sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
}

function compareItems(left, right) {
  const direction = sortDirection.value === 'asc' ? 1 : -1

  if (sortBy.value === 'type') {
    return String(left.type).localeCompare(String(right.type)) * direction
  }

  if (sortBy.value === 'modified_at') {
    return ((Number(left.modified_at ?? 0) - Number(right.modified_at ?? 0)) * direction)
  }

  return String(left.name ?? '').localeCompare(String(right.name ?? ''), undefined, {
    numeric: true,
    sensitivity: 'base',
  }) * direction
}

function notifyError(message) {
  if (typeof Nova.error === 'function') {
    Nova.error(message)
    return
  }

  window.alert(message)
}

function thumbnailUrl(selectedPath) {
  return `${apiBase}/preview?area=${encodeURIComponent(area.value)}&path=${encodeURIComponent(selectedPath)}&thumbnail=1`
}

function formatSize(size) {
  if (!Number.isFinite(size)) {
    return 'File'
  }

  return size < 1024 * 1024
    ? `${(size / 1024).toFixed(1)} KB`
    : `${(size / 1024 / 1024).toFixed(1)} MB`
}
</script>
