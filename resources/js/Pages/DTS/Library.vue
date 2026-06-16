<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import DTSLayout from '@/Layouts/DTSLayout.vue'

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            for_receiving: 0,
            received: 0,
            returned: 0,
        }),
    },
    offices: {
        type: Array,
        default: () => [],
    },
    personnel: {
        type: Array,
        default: () => [],
    },
    docTypes: {
        type: Array,
        default: () => [],
    },
    attachments: {
        type: Array,
        default: () => [],
    },
    actionTypes: {
        type: Array,
        default: () => [],
    },
    // Backward support: some controller versions send this as addresses.
    addresses: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()

const userRights = computed(() => {
    return String(page.props.auth?.user?.rights ?? '').trim()
})

const canManageDts = computed(() => {
    return ['1', '3'].includes(userRights.value)
})

const currentParams = computed(() => {
    const queryString = page.url.includes('?') ? page.url.split('?')[1] : ''

    return new URLSearchParams(queryString)
})

const activeLibrary = computed(() => {
    return currentParams.value.get('tab') || 'personnel'
})

const selectedRows = ref([])
const search = ref('')
const personnelOfficeSearch = ref('')
const showPersonnelOfficeDropdown = ref(false)
const perPage = ref(10)
const currentPage = ref(1)
const showAddModal = ref(false)
const showEditModal = ref(false)
const showDeleteModal = ref(false)
const recordToEdit = ref(null)
const recordToDelete = ref(null)

const personnelForm = useForm({
    name: '',
    IDoffice: '',
})

const officeForm = useForm({
    officename: '',
    abbrev: '',
})

const docTypeForm = useForm({
    code: '',
    description: '',
})

const attachmentForm = useForm({
    code: '',
    name: '',
    description: '',
})

const actionTypeForm = useForm({
    name: '',
    description: '',
})

const deleteForm = useForm({
    ids: [],
})

const libraryMenus = [
    { key: 'personnel', label: 'Personnel' },
    { key: 'office', label: 'Office' },
    { key: 'doctype', label: 'Doc Type' },
    { key: 'attachment', label: 'Attachment' },
    { key: 'action-types', label: 'Action Taken' },
]

const activeTitle = computed(() => {
    return libraryMenus.find((item) => item.key === activeLibrary.value)?.label || 'Personnel'
})

const libraryTabHref = (tab) => {
    return `/dts/library?tab=${tab}`
}

const canManageActiveLibrary = computed(() => {
    return canManageDts.value && ['personnel', 'office', 'doctype', 'attachment', 'action-types'].includes(activeLibrary.value)
})

const getValue = (row, keys) => {
    for (const key of keys) {
        const value = row?.[key]

        if (value !== undefined && value !== null && String(value).trim() !== '') {
            return value
        }
    }

    return null
}

const normalizedPersonnel = computed(() => {
    return (props.personnel || []).map((person, index) => ({
        ID: person.ID ?? person.id ?? `person-${index}`,
        name: getValue(person, ['personnel_name', 'name', 'Name', 'fullname']) || '-',
        IDoffice: person.IDoffice ?? person.office_id ?? '',
        officename: person.officename ?? person.office_name ?? 'not applicable',
    }))
})

const normalizedOffices = computed(() => {
    return (props.offices || []).map((office, index) => ({
        ID: office.ID ?? office.id ?? `office-${index}`,
        officename: office.officename ?? office.office_name ?? office.name ?? '-',
        abbrev: office.abbrev ?? office.abbreviation ?? '',
        personnel_count: office.personnel_count ?? office.count ?? 0,
    }))
})

const filteredPersonnelOffices = computed(() => {
    const keyword = String(personnelOfficeSearch.value || '').toLowerCase().trim()

    if (!keyword) {
        return normalizedOffices.value
    }

    return normalizedOffices.value.filter((office) => {
        return String(office.officename || '').toLowerCase().includes(keyword)
            || String(office.abbrev || '').toLowerCase().includes(keyword)
    })
})

const displayedPersonnelOffices = computed(() => {
    return filteredPersonnelOffices.value.slice(0, 20)
})

const handlePersonnelOfficeSearchInput = () => {
    personnelForm.IDoffice = ''
    showPersonnelOfficeDropdown.value = true
}

const selectPersonnelOffice = (office) => {
    personnelForm.IDoffice = office.ID
    personnelOfficeSearch.value = `${office.officename}${office.abbrev ? ` (${office.abbrev})` : ''}`
    showPersonnelOfficeDropdown.value = false
}

const normalizedDocTypes = computed(() => {
    return (props.docTypes || []).map((item, index) => ({
        ID: item.ID ?? item.id ?? `doctype-${index}`,
        code: item.code ?? '',
        displayName: item.description ?? item.name ?? '-',
        description: item.description ?? '',
    }))
})

const normalizedAttachments = computed(() => {
    return (props.attachments || []).map((item, index) => ({
        ID: item.ID ?? item.id ?? `attachment-${index}`,
        code: item.code ?? '',
        displayName:
            item.name ??
            item.attachment ??
            item.title ??
            item.description ??
            item.filename ??
            '-',
        description:
            item.description ??
            item.details ??
            item.remarks ??
            '',
    }))
})

const normalizedActionTypes = computed(() => {
    const records = props.actionTypes && props.actionTypes.length
        ? props.actionTypes
        : (props.addresses || [])

    return records.map((item, index) => ({
        ID: item.ID ?? item.id ?? `action-type-${index}`,
        name: item.name ?? item.action_name ?? item.label ?? item.description ?? '-',
        displayName: item.name ?? item.action_name ?? item.label ?? item.description ?? '-',
        description: item.description ?? item.details ?? item.remarks ?? '',
    }))
})

const sourceRows = computed(() => {
    if (activeLibrary.value === 'personnel') return normalizedPersonnel.value
    if (activeLibrary.value === 'office') return normalizedOffices.value
    if (activeLibrary.value === 'doctype') return normalizedDocTypes.value
    if (activeLibrary.value === 'attachment') return normalizedAttachments.value
    if (activeLibrary.value === 'action-types') return normalizedActionTypes.value

    return []
})

const filteredRows = computed(() => {
    const keyword = String(search.value || '').toLowerCase().trim()

    if (!keyword) {
        return sourceRows.value
    }

    return sourceRows.value.filter((row) => {
        return Object.values(row).some((value) => {
            return String(value || '').toLowerCase().includes(keyword)
        })
    })
})

const totalRows = computed(() => filteredRows.value.length)

const totalPages = computed(() => {
    return Math.max(1, Math.ceil(totalRows.value / Number(perPage.value || 10)))
})

const paginatedRows = computed(() => {
    const limit = Number(perPage.value || 10)
    const start = (currentPage.value - 1) * limit
    const end = start + limit

    return filteredRows.value.slice(start, end)
})

const paginationFrom = computed(() => {
    if (totalRows.value === 0) return 0

    return (currentPage.value - 1) * Number(perPage.value || 10) + 1
})

const paginationTo = computed(() => {
    return Math.min(currentPage.value * Number(perPage.value || 10), totalRows.value)
})

const visiblePages = computed(() => {
    const pages = []
    const maxButtons = 7

    let start = Math.max(1, currentPage.value - 3)
    let end = Math.min(totalPages.value, start + maxButtons - 1)

    if (end - start < maxButtons - 1) {
        start = Math.max(1, end - maxButtons + 1)
    }

    for (let page = start; page <= end; page++) {
        pages.push(page)
    }

    return pages
})

const activeFormProcessing = computed(() => {
    return personnelForm.processing
        || officeForm.processing
        || docTypeForm.processing
        || attachmentForm.processing
        || actionTypeForm.processing
})

const genericColumns = computed(() => {
    if (activeLibrary.value === 'doctype') {
        return [
            { key: 'code', label: 'Code' },
            { key: 'displayName', label: 'Description' },
        ]
    }

    if (activeLibrary.value === 'attachment') {
        return [
            { key: 'code', label: 'Code' },
            { key: 'displayName', label: 'Attachment' },
            { key: 'description', label: 'Description' },
        ]
    }

    if (activeLibrary.value === 'action-types') {
        return [
            { key: 'displayName', label: 'Action Taken' },
            { key: 'description', label: 'Description' },
        ]
    }

    return []
})

watch([activeLibrary, search, perPage], () => {
    currentPage.value = 1
    selectedRows.value = []
})

const goToPage = (pageNumber) => {
    if (pageNumber < 1 || pageNumber > totalPages.value) return

    currentPage.value = pageNumber
}

const resetSearch = () => {
    search.value = ''
}

const rowKey = (row) => {
    return row.ID
}

const toggleRow = (row) => {
    if (!canManageActiveLibrary.value) return

    const key = rowKey(row)

    if (selectedRows.value.includes(key)) {
        selectedRows.value = selectedRows.value.filter((selectedKey) => selectedKey !== key)
        return
    }

    selectedRows.value.push(key)
}

const isSelected = (row) => {
    return selectedRows.value.includes(rowKey(row))
}

const resetLibraryForms = () => {
    personnelOfficeSearch.value = ''
    showPersonnelOfficeDropdown.value = false

    personnelForm.reset()
    personnelForm.clearErrors()

    officeForm.reset()
    officeForm.clearErrors()

    docTypeForm.reset()
    docTypeForm.clearErrors()

    attachmentForm.reset()
    attachmentForm.clearErrors()

    actionTypeForm.reset()
    actionTypeForm.clearErrors()
}

const closeRecordModal = () => {
    if (activeFormProcessing.value) return

    showAddModal.value = false
    showEditModal.value = false
    recordToEdit.value = null
    resetLibraryForms()
}

const openAddModal = () => {
    if (!canManageActiveLibrary.value) return

    resetLibraryForms()
    recordToEdit.value = null
    showEditModal.value = false
    showAddModal.value = true
}

const openEditModal = (row) => {
    if (!canManageActiveLibrary.value || !row) return

    resetLibraryForms()
    recordToEdit.value = row

    if (activeLibrary.value === 'personnel') {
        personnelForm.name = row.name || ''
        personnelForm.IDoffice = row.IDoffice || ''
        personnelOfficeSearch.value = row.officename && row.officename !== 'not applicable' ? row.officename : ''
        showPersonnelOfficeDropdown.value = false
    }

    if (activeLibrary.value === 'office') {
        officeForm.officename = row.officename || ''
        officeForm.abbrev = row.abbrev || ''
    }

    if (activeLibrary.value === 'doctype') {
        docTypeForm.code = row.code || ''
        docTypeForm.description = row.displayName || row.description || ''
    }

    if (activeLibrary.value === 'attachment') {
        attachmentForm.code = row.code || ''
        attachmentForm.name = row.displayName || row.name || ''
        attachmentForm.description = row.description || ''
    }

    if (activeLibrary.value === 'action-types') {
        actionTypeForm.name = row.displayName || row.name || ''
        actionTypeForm.description = row.description || ''
    }

    showAddModal.value = false
    showEditModal.value = true
}

const closeAddModal = () => {
    closeRecordModal()
}

const closeEditModal = () => {
    closeRecordModal()
}

const formForActiveLibrary = () => {
    if (activeLibrary.value === 'personnel') return personnelForm
    if (activeLibrary.value === 'office') return officeForm
    if (activeLibrary.value === 'doctype') return docTypeForm
    if (activeLibrary.value === 'attachment') return attachmentForm
    if (activeLibrary.value === 'action-types') return actionTypeForm

    return null
}

const storeUrlForActiveLibrary = () => {
    if (activeLibrary.value === 'personnel') return '/dts/library/personnel/store'
    if (activeLibrary.value === 'office') return '/dts/library/office/store'
    if (activeLibrary.value === 'doctype') return '/dts/library/doctype/store'
    if (activeLibrary.value === 'attachment') return '/dts/library/attachment/store'
    if (activeLibrary.value === 'action-types') return '/dts/library/action-types'

    return ''
}

const updateUrlForActiveLibrary = (id) => {
    if (activeLibrary.value === 'personnel') return `/dts/library/personnel/${id}/update`
    if (activeLibrary.value === 'office') return `/dts/library/office/${id}/update`
    if (activeLibrary.value === 'doctype') return `/dts/library/doctype/${id}/update`
    if (activeLibrary.value === 'attachment') return `/dts/library/attachment/${id}/update`
    if (activeLibrary.value === 'action-types') return `/dts/library/action-types/${id}`

    return ''
}

const deleteUrlForActiveLibrary = () => {
    if (activeLibrary.value === 'personnel') return '/dts/library/personnel/delete'
    if (activeLibrary.value === 'office') return '/dts/library/office/delete'
    if (activeLibrary.value === 'doctype') return '/dts/library/doctype/delete'
    if (activeLibrary.value === 'attachment') return '/dts/library/attachment/delete'
    if (activeLibrary.value === 'action-types') return '/dts/library/action-types'

    return ''
}

const saveRecord = () => {
    if (!canManageActiveLibrary.value) return

    const form = formForActiveLibrary()

    if (!form) return

    const isEditing = showEditModal.value && recordToEdit.value
    const url = isEditing
        ? updateUrlForActiveLibrary(rowKey(recordToEdit.value))
        : storeUrlForActiveLibrary()

    if (!url) return

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeRecordModal()
        },
    }

    if (isEditing && activeLibrary.value === 'action-types') {
        form.patch(url, options)
        return
    }

    form.post(url, options)
}

const openDeleteModal = (row) => {
    if (!canManageActiveLibrary.value) return

    recordToDelete.value = row
    showDeleteModal.value = true
}

const closeDeleteModal = () => {
    if (deleteForm.processing) return

    showDeleteModal.value = false
    recordToDelete.value = null
    deleteForm.reset()
    deleteForm.clearErrors()
}

const deleteModalTitle = computed(() => {
    if (activeLibrary.value === 'personnel') {
        return 'Delete Personnel Record'
    }

    if (activeLibrary.value === 'office') {
        return 'Delete Office Record'
    }

    if (activeLibrary.value === 'doctype') {
        return 'Delete Doc Type Record'
    }

    if (activeLibrary.value === 'attachment') {
        return 'Delete Attachment Record'
    }

    if (activeLibrary.value === 'action-types') {
        return 'Delete Action Taken Record'
    }

    return 'Delete Record'
})

const deleteModalMessage = computed(() => {
    if (!recordToDelete.value) {
        return ''
    }

    if (activeLibrary.value === 'personnel') {
        return `Are you sure you want to delete "${recordToDelete.value.name}"?`
    }

    if (activeLibrary.value === 'office') {
        return `Are you sure you want to delete "${recordToDelete.value.officename}"?`
    }

    if (activeLibrary.value === 'doctype') {
        return `Are you sure you want to delete "${recordToDelete.value.displayName}"?`
    }

    if (activeLibrary.value === 'attachment') {
        return `Are you sure you want to delete "${recordToDelete.value.displayName}"?`
    }

    if (activeLibrary.value === 'action-types') {
        return `Are you sure you want to delete "${recordToDelete.value.displayName}"?`
    }

    return 'Are you sure you want to delete this record?'
})

const confirmDeleteRecord = () => {
    if (!canManageActiveLibrary.value || !recordToDelete.value) return

    deleteForm.ids = [rowKey(recordToDelete.value)]

    const url = deleteUrlForActiveLibrary()

    if (!url) return

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            selectedRows.value = []
            showDeleteModal.value = false
            recordToDelete.value = null
        },
    }

    if (activeLibrary.value === 'action-types') {
        deleteForm.delete(url, options)
        return
    }

    deleteForm.post(url, options)
}

</script>

<template>
    <Head title="DTS Library" />

    <DTSLayout :stats="stats">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-screen-2xl px-6 py-5 lg:px-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                            Library - {{ activeTitle }}
                        </h1>

                        <p class="mt-2 text-sm text-slate-500">
                            Manage DTS library records.
                            <span class="font-semibold text-slate-700">
                                {{ totalRows }}
                            </span>
                            record(s) found.
                        </p>

                        <p
                            v-if="!canManageDts"
                            class="mt-3 inline-flex rounded-full border border-yellow-300 bg-yellow-50 px-4 py-2 text-xs font-bold uppercase tracking-wide text-yellow-800"
                        >
                            Viewer only access
                        </p>
                    </div>


                </div>
            </div>
        </header>

        <main class="mx-auto max-w-screen-2xl px-6 py-8 lg:px-8">
            <!-- LIBRARY TAB BUTTONS -->
            <div class="mb-6 rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="menu in libraryMenus"
                        :key="menu.key"
                        :href="libraryTabHref(menu.key)"
                        class="inline-flex min-h-[48px] items-center gap-3 rounded-2xl border px-5 py-3 text-sm font-bold transition-all"
                        :class="activeLibrary === menu.key
                            ? 'border-blue-600 bg-blue-600 text-white shadow-md shadow-blue-100'
                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700'"
                    >
                        <span class="whitespace-nowrap">
                            {{ menu.label }}
                        </span>

                    </Link>
                </div>
            </div>

            <!-- Search and Entries -->
            <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-1 flex-col gap-3 md:flex-row">
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="`Search ${activeTitle.toLowerCase()}...`"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />

                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="resetSearch"
                        >
                            Reset
                        </button>
                    </div>

                    <div class="flex items-center gap-3 text-sm text-slate-600">
                        <span class="font-medium">Show</span>

                        <div class="relative">
                            <select
                                v-model="perPage"
                                class="h-11 w-24 appearance-none rounded-xl border border-slate-300 bg-white px-4 pr-10 text-sm font-semibold text-slate-700 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="20">20</option>
                                <option :value="50">50</option>
                            </select>

                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-400">
                                ▼
                            </div>
                        </div>

                        <span class="font-medium">entries</span>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <!-- Personnel -->
                <div v-if="activeLibrary === 'personnel'" class="p-6">
                    <div
                        v-if="canManageActiveLibrary"
                        class="mb-4 flex justify-end"
                    >
                        <button
                            type="button"
                            class="inline-flex w-fit items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700"
                            @click="openAddModal"
                        >
                            + Add New Record
                        </button>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-700">
                                <tr>
                                    <th class="border-b border-slate-200 px-5 py-4 font-bold">
                                        Personnel
                                    </th>
                                    <th class="border-b border-slate-200 px-5 py-4 font-bold">
                                        Office
                                    </th>
                                    <th
                                        v-if="canManageActiveLibrary"
                                        class="w-[140px] border-b border-slate-200 px-5 py-4 text-center font-bold"
                                    >
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="person in paginatedRows"
                                    :key="`person-${person.ID}`"
                                    class="border-b border-slate-100 hover:bg-slate-50"
                                >
                                    <td class="px-5 py-4 align-top">
                                        <span class="font-semibold text-blue-700">
                                            {{ person.name }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 align-top text-slate-700">
                                        {{ person.officename || '-' }}
                                    </td>

                                    <td
                                        v-if="canManageActiveLibrary"
                                        class="px-5 py-4 text-center align-top"
                                    >
                                        <div class="flex items-center justify-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-black text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="activeFormProcessing"
                                                @click="openEditModal(person)"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-xs font-black text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="deleteForm.processing"
                                                @click="openDeleteModal(person)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="paginatedRows.length === 0">
                                    <td :colspan="canManageActiveLibrary ? 3 : 2" class="px-5 py-12 text-center text-sm text-slate-500">
                                        No personnel records found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Office -->
                <div v-else-if="activeLibrary === 'office'" class="p-6">
                    <div
                        v-if="canManageActiveLibrary"
                        class="mb-4 flex justify-end"
                    >
                        <button
                            type="button"
                            class="inline-flex w-fit items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700"
                            @click="openAddModal"
                        >
                            + Add New Record
                        </button>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-700">
                                <tr>
                                    <th class="border-b border-slate-200 px-5 py-4 font-bold">
                                        Offices
                                    </th>
                                    <th class="w-[180px] border-b border-slate-200 px-5 py-4 text-center font-bold">
                                        No. of Personnel
                                    </th>
                                    <th
                                        v-if="canManageActiveLibrary"
                                        class="w-[140px] border-b border-slate-200 px-5 py-4 text-center font-bold"
                                    >
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="office in paginatedRows"
                                    :key="`office-${office.ID}`"
                                    class="border-b border-slate-100 hover:bg-slate-50"
                                >
                                    <td class="px-5 py-4 align-top">
                                        <div class="font-semibold text-blue-700">
                                            {{ office.officename || '-' }}
                                        </div>

                                        <div
                                            v-if="office.abbrev"
                                            class="mt-1 text-xs text-slate-500"
                                        >
                                            {{ office.abbrev }}
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 text-center align-top font-semibold text-slate-700">
                                        {{ office.personnel_count || 0 }}
                                    </td>

                                    <td
                                        v-if="canManageActiveLibrary"
                                        class="px-5 py-4 text-center align-top"
                                    >
                                        <div class="flex items-center justify-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-black text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="activeFormProcessing"
                                                @click="openEditModal(office)"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-xs font-black text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="deleteForm.processing"
                                                @click="openDeleteModal(office)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="paginatedRows.length === 0">
                                    <td :colspan="canManageActiveLibrary ? 3 : 2" class="px-5 py-12 text-center text-sm text-slate-500">
                                        No office records found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Doc Type / Attachment -->
                <div v-else class="p-6">
                    <div
                        v-if="canManageActiveLibrary"
                        class="mb-4 flex justify-end"
                    >
                        <button
                            type="button"
                            class="inline-flex w-fit items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-blue-700"
                            @click="openAddModal"
                        >
                            + Add New Record
                        </button>
                    </div>

                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-700">
                                <tr>
                                    <th
                                        v-for="column in genericColumns"
                                        :key="column.key"
                                        class="border-b border-slate-200 px-5 py-4 font-bold"
                                    >
                                        {{ column.label }}
                                    </th>

                                    <th
                                        v-if="canManageActiveLibrary"
                                        class="w-[180px] border-b border-slate-200 px-5 py-4 text-center font-bold"
                                    >
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="item in paginatedRows"
                                    :key="`${activeLibrary}-${item.ID}`"
                                    class="border-b border-slate-100 hover:bg-slate-50"
                                >
                                    <td
                                        v-for="column in genericColumns"
                                        :key="column.key"
                                        class="px-5 py-4 align-top"
                                    >
                                        <span
                                            :class="column.key === 'displayName'
                                                ? 'font-semibold text-blue-700'
                                                : 'text-slate-700'"
                                        >
                                            {{ item[column.key] || '-' }}
                                        </span>
                                    </td>

                                    <td
                                        v-if="canManageActiveLibrary"
                                        class="px-5 py-4 text-center align-top"
                                    >
                                        <div class="flex items-center justify-center gap-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-black text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="activeFormProcessing"
                                                @click="openEditModal(item)"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-xs font-black text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                :disabled="deleteForm.processing"
                                                @click="openDeleteModal(item)"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="paginatedRows.length === 0">
                                    <td
                                        :colspan="canManageActiveLibrary ? genericColumns.length + 1 : genericColumns.length"
                                        class="px-5 py-12 text-center text-sm text-slate-500"
                                    >
                                        No {{ activeTitle.toLowerCase() }} records found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="flex flex-col gap-4 border-t border-slate-200 px-6 py-5 md:flex-row md:items-center md:justify-between">
                    <div class="text-sm text-slate-500">
                        Showing
                        <span class="font-semibold text-slate-700">
                            {{ paginationFrom }}
                        </span>
                        to
                        <span class="font-semibold text-slate-700">
                            {{ paginationTo }}
                        </span>
                        of
                        <span class="font-semibold text-slate-700">
                            {{ totalRows }}
                        </span>
                        entries
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="currentPage === 1"
                            @click="goToPage(currentPage - 1)"
                        >
                            Previous
                        </button>

                        <button
                            v-for="pageNumber in visiblePages"
                            :key="pageNumber"
                            type="button"
                            class="rounded-lg border px-3 py-2 text-sm font-semibold"
                            :class="pageNumber === currentPage
                                ? 'border-blue-600 bg-blue-600 text-white'
                                : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'"
                            @click="goToPage(pageNumber)"
                        >
                            {{ pageNumber }}
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="currentPage === totalPages"
                            @click="goToPage(currentPage + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <!-- Add/Edit Record Modal -->
        <div
            v-if="canManageActiveLibrary && (showAddModal || showEditModal)"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
        >
            <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">
                            {{ showEditModal ? 'Edit' : 'Add New' }} {{ activeTitle }}
                        </h3>

                        <p class="mt-1 text-sm text-slate-500">
                            {{ showEditModal ? 'Update the details below.' : 'Fill out the required details below.' }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100"
                        @click="closeRecordModal"
                    >
                        ✕
                    </button>
                </div>

                <!-- Personnel Form -->
                <div v-if="activeLibrary === 'personnel'" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Personnel Name
                        </label>

                        <input
                            v-model="personnelForm.name"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Enter personnel name"
                        />

                        <p
                            v-if="personnelForm.errors.name"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ personnelForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Office
                        </label>

                        <div class="relative">
                            <input
                                v-model="personnelOfficeSearch"
                                type="text"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 pr-10 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                placeholder="Type office name or abbreviation..."
                                autocomplete="off"
                                @focus="showPersonnelOfficeDropdown = true"
                                @input="handlePersonnelOfficeSearchInput"
                            />

                            <button
                                v-if="personnelOfficeSearch"
                                type="button"
                                class="absolute inset-y-0 right-3 text-sm font-black text-slate-400 hover:text-slate-700"
                                @click="personnelOfficeSearch = ''; personnelForm.IDoffice = ''; showPersonnelOfficeDropdown = true"
                            >
                                ✕
                            </button>

                            <div
                                v-if="showPersonnelOfficeDropdown"
                                class="absolute z-50 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl"
                            >
                                <button
                                    v-for="office in displayedPersonnelOffices"
                                    :key="office.ID"
                                    type="button"
                                    class="block w-full px-4 py-3 text-left text-sm hover:bg-blue-50"
                                    @mousedown.prevent="selectPersonnelOffice(office)"
                                >
                                    <span class="font-bold text-slate-800">
                                        {{ office.officename }}
                                    </span>

                                    <span
                                        v-if="office.abbrev"
                                        class="ml-1 text-xs font-semibold text-slate-500"
                                    >
                                        ({{ office.abbrev }})
                                    </span>
                                </button>

                                <div
                                    v-if="displayedPersonnelOffices.length === 0"
                                    class="px-4 py-4 text-sm font-semibold text-amber-700"
                                >
                                    No office found for "{{ personnelOfficeSearch }}".
                                </div>

                                <div
                                    v-if="filteredPersonnelOffices.length > displayedPersonnelOffices.length"
                                    class="border-t border-slate-100 px-4 py-2 text-xs font-semibold text-slate-500"
                                >
                                    Showing first 20 results. Type more to narrow the list.
                                </div>
                            </div>
                        </div>

                        <p
                            v-if="personnelForm.IDoffice"
                            class="mt-1 text-xs font-semibold text-green-700"
                        >
                            Selected office: {{ personnelOfficeSearch }}
                        </p>

                        <p
                            v-if="personnelForm.errors.IDoffice"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ personnelForm.errors.IDoffice }}
                        </p>
                    </div>
                </div>

                <!-- Office Form -->
                <div v-if="activeLibrary === 'office'" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Office Name
                        </label>

                        <input
                            v-model="officeForm.officename"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Enter office name"
                        />

                        <p
                            v-if="officeForm.errors.officename"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ officeForm.errors.officename }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Abbreviation
                        </label>

                        <input
                            v-model="officeForm.abbrev"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Enter abbreviation"
                        />

                        <p
                            v-if="officeForm.errors.abbrev"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ officeForm.errors.abbrev }}
                        </p>
                    </div>
                </div>

                <!-- Doc Type Form -->
                <div v-if="activeLibrary === 'doctype'" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Code
                        </label>

                        <input
                            v-model="docTypeForm.code"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Enter doc type code"
                        />

                        <p
                            v-if="docTypeForm.errors.code"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ docTypeForm.errors.code }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Description
                        </label>

                        <input
                            v-model="docTypeForm.description"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Enter doc type description"
                        />

                        <p
                            v-if="docTypeForm.errors.description"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ docTypeForm.errors.description }}
                        </p>
                    </div>
                </div>

                <!-- Attachment Form -->
                <div v-if="activeLibrary === 'attachment'" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Code
                        </label>

                        <input
                            v-model="attachmentForm.code"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Enter attachment code"
                        />

                        <p
                            v-if="attachmentForm.errors.code"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ attachmentForm.errors.code }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Attachment Name
                        </label>

                        <input
                            v-model="attachmentForm.name"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Enter attachment name"
                        />

                        <p
                            v-if="attachmentForm.errors.name"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ attachmentForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Description
                        </label>

                        <textarea
                            v-model="attachmentForm.description"
                            rows="3"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Optional description"
                        ></textarea>

                        <p
                            v-if="attachmentForm.errors.description"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ attachmentForm.errors.description }}
                        </p>
                    </div>
                </div>

                <!-- Action Taken Form -->
                <div v-if="activeLibrary === 'action-types'" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Action Taken
                        </label>

                        <input
                            v-model="actionTypeForm.name"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Example: Emailed, Filed, Called, Delivered"
                        />

                        <p
                            v-if="actionTypeForm.errors.name"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ actionTypeForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">
                            Description
                        </label>

                        <textarea
                            v-model="actionTypeForm.description"
                            rows="3"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            placeholder="Optional description"
                        ></textarea>

                        <p
                            v-if="actionTypeForm.errors.description"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ actionTypeForm.errors.description }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeRecordModal"
                    >
                        Cancel
                    </button>

                    <button
                        v-if="canManageActiveLibrary"
                        type="button"
                        class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                        :disabled="activeFormProcessing"
                        @click="saveRecord"
                    >
                        {{ showEditModal ? 'Update Record' : 'Save Record' }}
                    </button>
                </div>
            </div>
        </div>
        <!-- Delete Confirmation Modal -->
        <div
            v-if="canManageActiveLibrary && showDeleteModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
        >
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="border-b border-red-100 bg-red-50 px-6 py-5">
                    <h3 class="text-xl font-black text-red-800">
                        {{ deleteModalTitle }}
                    </h3>

                    <p class="mt-1 text-sm font-semibold text-red-600">
                        This action cannot be undone.
                    </p>
                </div>

                <div class="px-6 py-5">
                    <p class="text-sm font-semibold leading-6 text-slate-700">
                        {{ deleteModalMessage }}
                    </p>

                    <div
                        v-if="deleteForm.errors.ids"
                        class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700"
                    >
                        {{ deleteForm.errors.ids }}
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                            :disabled="deleteForm.processing"
                            @click="closeDeleteModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            class="rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-50"
                            :disabled="deleteForm.processing"
                            @click="confirmDeleteRecord"
                        >
                            {{ deleteForm.processing ? 'Deleting...' : 'Yes, Delete' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </DTSLayout>
</template>