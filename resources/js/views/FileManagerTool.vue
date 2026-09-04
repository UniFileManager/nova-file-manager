<template>
  <div class="ufm-nova">
    <header class="ufm-nova__header">
      <div>
        <p class="ufm-nova__eyebrow">File library</p>
        <h1 class="ufm-nova__title">{{ currentTitle }}</h1>
        <nav class="ufm-nova__breadcrumbs" aria-label="Current folder">
          <button type="button" @click="openFolder('')">Main Library</button>
          <template v-for="crumb in breadcrumbs" :key="crumb.path">
            <span>/</span>
            <button type="button" @click="openFolder(crumb.path)">{{ crumb.name }}</button>
          </template>
        </nav>
      </div>

      <div class="ufm-nova__header-actions">
        <div v-if="storageAreas.length >= 1" class="ufm-nova__area-dropdown">
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
              v-for="storageArea in storageAreas"
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
          Upload files
        </button>
        <button type="button" class="ufm-nova__button" @click="openCreateFolderModal">
          New folder
        </button>
      </div>
    </header>

    <section class="ufm-nova__toolbar">
      <input
        v-model="search"
        class="ufm-nova__search"
        type="search"
        placeholder="Search this folder"
      />

      <div class="ufm-nova__toolbar-actions">
        <select v-model="sortBy" class="ufm-nova__select" aria-label="Sort files">
          <option value="name">Name</option>
          <option value="modified_at">Last modified</option>
          <option value="type">Type</option>
        </select>
        <button type="button" class="ufm-nova__button is-secondary" @click="toggleSortDirection">
          {{ sortDirection === 'asc' ? 'Ascending' : 'Descending' }}
        </button>
        <button
          type="button"
          class="ufm-nova__button"
          :disabled="selectedPaths.length === 0"
          @click="deleteSelected"
        >
          Delete selected
        </button>
      </div>
    </section>

    <section v-if="selectedPaths.length > 0" class="ufm-nova__selection-bar">
      <strong>{{ selectedPaths.length }} selected</strong>
      <button type="button" @click="clearSelection">Clear selection</button>
    </section>

    <section v-if="isLoading" class="ufm-nova__empty">Loading files…</section>

    <section v-else-if="filteredItems.length === 0" class="ufm-nova__empty">
      <div class="ufm-nova__empty-icon">📁</div>
      <h2>This folder is empty</h2>
      <p>Upload a file or create a folder to get started.</p>
    </section>

    <template v-else>
      <section class="ufm-nova__section">
        <header class="ufm-nova__section-header">
          <div>
            <h2>Folders</h2>
            <p>Keep related files organised in one place.</p>
          </div>
          <button type="button" class="ufm-nova__link-action" @click="openCreateFolderModal">+ New folder</button>
        </header>

        <div class="ufm-nova__folder-grid">
          <article
            v-for="item in visibleFolders"
            :key="item.path"
            class="ufm-nova__folder-card"
            :class="{ 'is-selected': selectedPaths.includes(item.path) }"
          >
            <button type="button" class="ufm-nova__folder-open" @click="openFolder(item.path)">
              <span class="ufm-nova__folder-icon">📁</span>
              <span>
                <strong :title="item.name">{{ item.name }}</strong>
                <small>Folder</small>
              </span>
              <span class="ufm-nova__folder-arrow">→</span>
            </button>

            <div class="ufm-nova__card-actions is-floating">
              <button type="button" @click="toggleSelected(item)">
                {{ selectedPaths.includes(item.path) ? 'Deselect' : 'Select' }}
              </button>
              <button type="button" @click="openMoveModal(item)">Move</button>
              <button type="button" @click="openRenameModal(item)">Rename</button>
              <button type="button" @click="deleteItems([item.path])">Delete</button>
            </div>
          </article>

          <button type="button" class="ufm-nova__add-folder-card" @click="openCreateFolderModal">
            + Add new folder
          </button>
        </div>

        <footer v-if="folderPageCount > 1" class="ufm-nova__pagination">
          <button type="button" :disabled="folderPage === 1" @click="folderPage = 1">First</button>
          <button type="button" :disabled="folderPage === 1" @click="folderPage -= 1">Previous</button>
          <span>Page {{ folderPage }} of {{ folderPageCount }}</span>
          <button type="button" :disabled="folderPage === folderPageCount" @click="folderPage += 1">Next</button>
          <button type="button" :disabled="folderPage === folderPageCount" @click="folderPage = folderPageCount">Last</button>
        </footer>
      </section>

      <section class="ufm-nova__section">
        <header class="ufm-nova__section-header">
          <div>
            <h2>Files</h2>
            <p>Select a file to preview it, or use actions to manage it.</p>
          </div>
          <span>{{ files.length }} files</span>
        </header>

        <div v-if="visibleFiles.length === 0" class="ufm-nova__empty is-compact">
          No files in this folder.
        </div>

        <div v-else class="ufm-nova__grid">
          <article
            v-for="item in visibleFiles"
            :key="item.path"
            class="ufm-nova__card"
            :class="{ 'is-selected': selectedPaths.includes(item.path) }"
          >
            <button type="button" class="ufm-nova__preview" @click="handleItemClick(item)">
              <img
                v-if="isImage(item)"
                :src="item.thumbnail_url"
                :alt="item.name"
                loading="lazy"
              />
              <span v-else class="ufm-nova__file-icon" :data-kind="documentKind(item)">
                {{ fileLabel(item) }}
              </span>
              <span v-if="selectedPaths.includes(item.path)" class="ufm-nova__check">✓</span>
            </button>

            <div class="ufm-nova__card-body">
              <strong :title="item.name">{{ item.name }}</strong>
              <span>{{ formatSize(item.size) }}</span>
            </div>

            <div class="ufm-nova__card-actions">
              <button type="button" @click="toggleSelected(item)">
                {{ selectedPaths.includes(item.path) ? 'Deselect' : 'Select' }}
              </button>
              <button type="button" @click="previewItem(item)">Preview</button>
              <a v-if="item.download_url" :href="item.download_url">Download</a>
              <button type="button" @click="openMoveModal(item)">Move</button>
              <button type="button" @click="openRenameModal(item)">Rename</button>
              <button type="button" @click="deleteItems([item.path])">Delete</button>
            </div>
          </article>
        </div>

        <footer v-if="filePageCount > 1" class="ufm-nova__pagination">
          <button type="button" :disabled="filePage === 1" @click="filePage = 1">First</button>
          <button type="button" :disabled="filePage === 1" @click="filePage -= 1">Previous</button>
          <span>Page {{ filePage }} of {{ filePageCount }}</span>
          <button type="button" :disabled="filePage === filePageCount" @click="filePage += 1">Next</button>
          <button type="button" :disabled="filePage === filePageCount" @click="filePage = filePageCount">Last</button>
        </footer>
      </section>
    </template>

    <p v-if="error" class="ufm-nova__error">{{ error }}</p>

    <div v-if="isUploadModalOpen" class="ufm-nova__modal-backdrop" @click.self="closeUploadModal">
      <section class="ufm-nova__modal is-upload" role="dialog" aria-modal="true" aria-label="Upload files">
        <header class="ufm-nova__modal-header">
          <div>
            <p>Upload files</p>
            <h2>Add files to {{ currentTitle }}</h2>
          </div>
          <button type="button" @click="closeUploadModal">×</button>
        </header>

        <div class="ufm-nova__upload-body">
          <label
            class="ufm-nova__dropzone"
            @dragover.prevent
            @drop.prevent="uploadDroppedFiles"
          >
            <input type="file" multiple @change="uploadFiles" />
            <span>⇧</span>
            <strong>Choose files or drag and drop here</strong>
            <small>Upload up to {{ novaConfig.maxUploadFiles ?? 10 }} files at a time.</small>
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

    <div v-if="previewedItem" class="ufm-nova__modal-backdrop" @click.self="previewedItem = null">
      <section class="ufm-nova__modal" role="dialog" aria-modal="true" aria-label="File preview">
        <header class="ufm-nova__modal-header">
          <div>
            <p>File preview</p>
            <h2>{{ previewedItem.name }}</h2>
          </div>
          <button type="button" @click="previewedItem = null">×</button>
        </header>
        <div class="ufm-nova__preview-modal-body">
          <div class="ufm-nova__preview-stage">
            <img v-if="isImage(previewedItem)" :src="previewedItem.preview_url" :alt="previewedItem.name" />
            <iframe v-else-if="canInlinePreview(previewedItem)" :src="previewedItem.preview_url" :title="previewedItem.name" />
            <div v-else class="ufm-nova__empty">
              Preview is not available for this file type.
            </div>
          </div>
          <aside class="ufm-nova__details-panel">
            <h3>File details</h3>
            <dl>
              <div>
                <dt>Type</dt>
                <dd>{{ previewedItem.mime_type ?? 'File' }}</dd>
              </div>
              <div>
                <dt>Size</dt>
                <dd>{{ formatSize(previewedItem.size) }}</dd>
              </div>
              <div>
                <dt>Modified</dt>
                <dd>{{ formatDate(previewedItem.modified_at) }}</dd>
              </div>
              <div>
                <dt>Relative path</dt>
                <dd><code>{{ previewedItem.path }}</code></dd>
              </div>
            </dl>
            <div class="ufm-nova__details-actions">
              <a v-if="previewedItem.download_url" class="ufm-nova__button" :href="previewedItem.download_url">Download</a>
              <button type="button" class="ufm-nova__button is-secondary" @click="openRenameModal(previewedItem)">Rename</button>
              <button type="button" class="ufm-nova__button is-secondary" @click="openMoveModal(previewedItem)">Move</button>
            </div>
          </aside>
        </div>
      </section>
    </div>

    <div v-if="nameModalMode" class="ufm-nova__modal-backdrop" @click.self="closeNameModal">
      <section class="ufm-nova__modal is-small" role="dialog" aria-modal="true" :aria-label="nameModalTitle">
        <header class="ufm-nova__modal-header">
          <div>
            <p>{{ nameModalEyebrow }}</p>
            <h2>{{ nameModalTitle }}</h2>
          </div>
          <button type="button" @click="closeNameModal">×</button>
        </header>

        <form class="ufm-nova__name-form" @submit.prevent="submitNameModal">
          <label>
            Name
            <span class="ufm-nova__input-group" :class="{ 'has-suffix': lockedExtension }">
              <input
                ref="nameInput"
                v-model="nameValue"
                class="ufm-nova__input"
                type="text"
                autocomplete="off"
                placeholder="Folder name"
              />
              <span v-if="lockedExtension" class="ufm-nova__locked-extension">{{ lockedExtension }}</span>
            </span>
          </label>
          <p v-if="lockedExtension" class="ufm-nova__field-help">
            The file extension is kept automatically.
          </p>
          <p v-if="nameError" class="ufm-nova__error">{{ nameError }}</p>

          <footer class="ufm-nova__modal-footer">
            <button type="button" class="ufm-nova__button is-secondary" @click="closeNameModal">
              Cancel
            </button>
            <button type="submit" class="ufm-nova__button" :disabled="isNameSaving">
              {{ isNameSaving ? 'Saving…' : nameModalSubmitLabel }}
            </button>
          </footer>
        </form>
      </section>
    </div>

    <div v-if="movingItem" class="ufm-nova__modal-backdrop" @click.self="closeMoveModal">
      <section class="ufm-nova__modal is-small" role="dialog" aria-modal="true" aria-label="Move item">
        <header class="ufm-nova__modal-header">
          <div>
            <p>Move item</p>
            <h2>{{ movingItem.name }}</h2>
          </div>
          <button type="button" @click="closeMoveModal">×</button>
        </header>
        <div class="ufm-nova__move-body">
          <label>
            Destination folder
            <select v-model="moveDestination" class="ufm-nova__select">
              <option v-for="destination in moveDestinations" :key="destination.path" :value="destination.path">
                {{ destination.label }}
              </option>
            </select>
          </label>
          <p v-if="moveDestinations.length === 0">There are no available folders for this item.</p>
        </div>
        <footer class="ufm-nova__modal-footer">
          <button type="button" class="ufm-nova__button is-secondary" @click="closeMoveModal">Cancel</button>
          <button type="button" class="ufm-nova__button" :disabled="moveDestinations.length === 0" @click="moveItem">
            Move here
          </button>
        </footer>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'

const novaConfig = Nova.config('unifilemanager') ?? {}
const apiBase = novaConfig.apiBase ?? '/nova-vendor/unifilemanager/nova-file-manager'
const area = ref(novaConfig.defaultArea ?? 'private')
const path = ref('')
const items = ref([])
const storageAreas = ref(novaConfig.storageAreas ?? [])
const search = ref('')
const selectedPaths = ref([])
const isLoading = ref(false)
const error = ref('')
const sortBy = ref(window.localStorage.getItem('ufm:nova:sort-by') ?? 'name')
const sortDirection = ref(window.localStorage.getItem('ufm:nova:sort-direction') ?? 'asc')
const folderPage = ref(1)
const filePage = ref(1)
const folderPerPage = 10
const filePerPage = 10
const previewedItem = ref(null)
const movingItem = ref(null)
const moveDestinations = ref([])
const moveDestination = ref('')
const isUploadModalOpen = ref(false)
const isUploading = ref(false)
const uploadProgress = ref(0)
const recentUploads = ref([])
const isStorageMenuOpen = ref(false)
const nameModalMode = ref('')
const nameModalItem = ref(null)
const nameValue = ref('')
const nameError = ref('')
const isNameSaving = ref(false)
const nameInput = ref(null)

const breadcrumbs = computed(() => {
  if (!path.value) {
    return []
  }

  return path.value.split('/').map((name, index, parts) => ({
    name,
    path: parts.slice(0, index + 1).join('/'),
  }))
})

const currentTitle = computed(() => {
  if (!path.value) {
    return 'Main Library'
  }

  return path.value.split('/').at(-1)
})

const activeStorageArea = computed(() => storageAreas.value.find((storageArea) => storageArea.key === area.value))

const nameModalEyebrow = computed(() => (nameModalMode.value === 'create' ? 'New folder' : 'Rename item'))
const nameModalTitle = computed(() => (nameModalMode.value === 'create' ? 'Create a folder' : `Rename ${nameModalItem.value?.name ?? 'item'}`))
const nameModalSubmitLabel = computed(() => (nameModalMode.value === 'create' ? 'Create folder' : 'Save name'))
const lockedExtension = computed(() => {
  if (nameModalMode.value !== 'rename' || nameModalItem.value?.type !== 'file') {
    return ''
  }

  const extension = fileExtension(nameModalItem.value.name)

  return extension === '' ? '' : `.${extension}`
})

const filteredItems = computed(() => {
  const term = search.value.trim().toLowerCase()
  const filtered = term
    ? items.value.filter((item) => item.name.toLowerCase().includes(term))
    : [...items.value]

  return filtered.sort((left, right) => compareItems(left, right))
})

const folders = computed(() => filteredItems.value.filter((item) => item.type === 'directory'))
const files = computed(() => filteredItems.value.filter((item) => item.type === 'file'))

const folderPageCount = computed(() => Math.max(1, Math.ceil(folders.value.length / folderPerPage)))
const filePageCount = computed(() => Math.max(1, Math.ceil(files.value.length / filePerPage)))

const visibleFolders = computed(() => {
  const start = (folderPage.value - 1) * folderPerPage

  return folders.value.slice(start, start + folderPerPage)
})

const visibleFiles = computed(() => {
  const start = (filePage.value - 1) * filePerPage

  return files.value.slice(start, start + filePerPage)
})

watch([search, sortBy, sortDirection], () => {
  folderPage.value = 1
  filePage.value = 1
  window.localStorage.setItem('ufm:nova:sort-by', sortBy.value)
  window.localStorage.setItem('ufm:nova:sort-direction', sortDirection.value)
})

watch(folderPageCount, (nextPageCount) => {
  if (folderPage.value > nextPageCount) {
    folderPage.value = nextPageCount
  }
})

watch(filePageCount, (nextPageCount) => {
  if (filePage.value > nextPageCount) {
    filePage.value = nextPageCount
  }
})

onMounted(async () => {
  await loadStorageAreas()
  await loadItems()
})

async function loadStorageAreas() {
  const { data } = await Nova.request().get(`${apiBase}/storage-areas`)
  storageAreas.value = data.data ?? []

  if (!storageAreas.value.some((storageArea) => storageArea.key === area.value)) {
    area.value = storageAreas.value.find((storageArea) => storageArea.default)?.key
      ?? storageAreas.value[0]?.key
      ?? area.value
  }
}

async function loadItems() {
  isLoading.value = true
  error.value = ''

  try {
    const { data } = await Nova.request().get(`${apiBase}/items`, {
      params: { area: area.value, path: path.value },
    })

    items.value = data.data ?? []
  } catch (exception) {
    error.value = messageFrom(exception, 'The folder could not be loaded.')
  } finally {
    isLoading.value = false
  }
}

function changeArea(nextArea) {
  area.value = nextArea
  path.value = ''
  selectedPaths.value = []
  previewedItem.value = null
  isStorageMenuOpen.value = false
  loadItems()
}

function storageIcon(storageArea) {
  return storageArea?.visibility === 'public' ? '▣' : '▢'
}

function openFolder(nextPath) {
  path.value = nextPath
  folderPage.value = 1
  filePage.value = 1
  selectedPaths.value = []
  loadItems()
}

function handleItemClick(item) {
  if (item.type === 'directory') {
    openFolder(item.path)
    return
  }

  previewItem(item)
}

function previewItem(item) {
  previewedItem.value = item
}

async function openCreateFolderModal() {
  nameModalMode.value = 'create'
  nameModalItem.value = null
  nameValue.value = 'New folder'
  nameError.value = ''
  await focusNameInput()
}

async function openRenameModal(item) {
  nameModalMode.value = 'rename'
  nameModalItem.value = item
  nameValue.value = item.type === 'file' ? fileBaseName(item.name) : item.name
  nameError.value = ''
  await focusNameInput()
}

async function focusNameInput() {
  await nextTick()
  nameInput.value?.focus()
  nameInput.value?.select()
}

function closeNameModal() {
  if (isNameSaving.value) {
    return
  }

  nameModalMode.value = ''
  nameModalItem.value = null
  nameValue.value = ''
  nameError.value = ''
}

async function submitNameModal() {
  const baseName = nameValue.value.trim()
  const name = nameModalMode.value === 'rename' && nameModalItem.value?.type === 'file'
    ? `${baseName}${lockedExtension.value}`
    : baseName

  if (baseName === '') {
    nameError.value = 'Enter a name before saving.'
    return
  }

  if (nameModalMode.value === 'rename' && nameModalItem.value?.name === name) {
    closeNameModal()
    return
  }

  isNameSaving.value = true
  nameError.value = ''
  let shouldClose = false

  try {
    if (nameModalMode.value === 'create') {
      await createFolder(name)
      shouldClose = true
    } else if (nameModalMode.value === 'rename' && nameModalItem.value) {
      await renameItem(nameModalItem.value, name)
      shouldClose = true
    }
  } finally {
    isNameSaving.value = false

    if (shouldClose) {
      closeNameModal()
    }
  }
}

async function createFolder(name) {
  error.value = ''

  try {
    await Nova.request().post(`${apiBase}/folders`, {
      area: area.value,
      path: path.value,
      name,
    })
    await loadItems()
  } catch (exception) {
    nameError.value = messageFrom(exception, 'The folder could not be created.')
    throw exception
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
  const chosenFiles = [...(event.target.files ?? [])]
  event.target.value = ''

  await uploadSelectedFiles(chosenFiles)
}

async function uploadDroppedFiles(event) {
  await uploadSelectedFiles([...(event.dataTransfer?.files ?? [])])
}

async function uploadSelectedFiles(chosenFiles) {
  if (chosenFiles.length === 0) {
    return
  }

  if (chosenFiles.length > Number(novaConfig.maxUploadFiles ?? 10)) {
    error.value = `Choose up to ${novaConfig.maxUploadFiles ?? 10} files at a time.`
    return
  }

  isUploading.value = true
  uploadProgress.value = 0

  for (const [index, file] of chosenFiles.entries()) {
    const form = new FormData()
    form.append('area', area.value)
    form.append('path', path.value)
    form.append('file', file)

    try {
      const { data } = await Nova.request().post(`${apiBase}/uploads`, form)
      recentUploads.value = [
        await fetchUploadedItem(data.path),
        ...recentUploads.value,
      ]
      uploadProgress.value = Math.round(((index + 1) / chosenFiles.length) * 100)
    } catch (exception) {
      error.value = messageFrom(exception, `${file.name} could not be uploaded.`)
    }
  }

  await loadItems()
  isUploading.value = false
}

async function fetchUploadedItem(uploadedPath) {
  const { data } = await Nova.request().get(`${apiBase}/items`, {
    params: { area: area.value, path: path.value },
  })

  return (data.data ?? []).find((item) => item.path === uploadedPath) ?? {
    name: uploadedPath.split('/').at(-1),
    path: uploadedPath,
    type: 'file',
  }
}

async function renameItem(item, name) {
  try {
    const { data } = await Nova.request().patch(`${apiBase}/items/rename`, {
      area: area.value,
      path: item.path,
      name,
    })
    await loadItems()

    if (previewedItem.value?.path === item.path) {
      previewedItem.value = items.value.find((current) => current.path === data.path) ?? null
    }
  } catch (exception) {
    nameError.value = messageFrom(exception, 'The item could not be renamed.')
    throw exception
  }
}

async function openMoveModal(item) {
  movingItem.value = item
  previewedItem.value = null
  moveDestinations.value = []
  moveDestination.value = ''

  try {
    const { data } = await Nova.request().get(`${apiBase}/items/move-destinations`, {
      params: { area: area.value, path: item.path },
    })

    moveDestinations.value = data.data ?? []
    moveDestination.value = moveDestinations.value[0]?.path ?? ''
  } catch (exception) {
    error.value = messageFrom(exception, 'Move destinations could not be loaded.')
    closeMoveModal()
  }
}

function closeMoveModal() {
  movingItem.value = null
  moveDestinations.value = []
  moveDestination.value = ''
}

async function moveItem() {
  if (!movingItem.value) {
    return
  }

  try {
    await Nova.request().patch(`${apiBase}/items/move`, {
      area: area.value,
      path: movingItem.value.path,
      destination: moveDestination.value,
    })
    closeMoveModal()
    selectedPaths.value = []
    await loadItems()
  } catch (exception) {
    error.value = messageFrom(exception, 'The item could not be moved.')
  }
}

async function deleteSelected() {
  await deleteItems(selectedPaths.value)
}

async function deleteItems(paths) {
  if (paths.length === 0 || !window.confirm(`This permanently deletes ${paths.length} selected item(s). This action cannot be undone.`)) {
    return
  }

  try {
    const { data } = await Nova.request().delete(`${apiBase}/items`, {
      data: { area: area.value, paths },
    })
    selectedPaths.value = []
    await loadItems()

    if ((data.non_empty_folders ?? 0) > 0) {
      error.value = `${data.non_empty_folders} non-empty folder(s) could not be deleted. Delete or move their contents first.`
    }
  } catch (exception) {
    error.value = messageFrom(exception, 'The selected items could not be deleted.')
  }
}

function toggleSelected(item) {
  selectedPaths.value = selectedPaths.value.includes(item.path)
    ? selectedPaths.value.filter((path) => path !== item.path)
    : [...selectedPaths.value, item.path]
}

function clearSelection() {
  selectedPaths.value = []
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

  return left.name.localeCompare(right.name, undefined, { numeric: true, sensitivity: 'base' }) * direction
}

function isImage(item) {
  return item.type === 'file' && String(item.mime_type ?? '').startsWith('image/')
}

function canInlinePreview(item) {
  return ['application/pdf', 'text/plain'].includes(String(item.mime_type ?? ''))
}

function documentKind(item) {
  if (item.type === 'directory') {
    return 'folder'
  }

  const extension = item.name.split('.').pop()?.toLowerCase()

  if (extension === 'pdf') return 'pdf'
  if (['doc', 'docx'].includes(extension)) return 'doc'
  if (['xls', 'xlsx', 'csv'].includes(extension)) return 'sheet'
  if (extension === 'txt') return 'text'

  return 'file'
}

function fileExtension(name) {
  const filename = String(name ?? '').split('/').at(-1)
  const lastDot = filename.lastIndexOf('.')

  return lastDot > 0 ? filename.slice(lastDot + 1) : ''
}

function fileBaseName(name) {
  const filename = String(name ?? '')
  const lastDot = filename.lastIndexOf('.')

  return lastDot > 0 ? filename.slice(0, lastDot) : filename
}

function fileLabel(item) {
  const kind = documentKind(item)

  return kind === 'file' ? 'FILE' : kind.toUpperCase()
}

function formatSize(size) {
  if (!Number.isFinite(size)) {
    return 'File'
  }

  if (size < 1024) {
    return `${size} B`
  }

  if (size < 1024 * 1024) {
    return `${(size / 1024).toFixed(1)} KB`
  }

  return `${(size / 1024 / 1024).toFixed(1)} MB`
}

function formatDate(timestamp) {
  if (!timestamp) {
    return 'Unknown'
  }

  return new Date(timestamp * 1000).toLocaleDateString()
}

function messageFrom(exception, fallback) {
  return exception?.response?.data?.message ?? fallback
}
</script>
