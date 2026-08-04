<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SearchableSelect from '@/Components/DTS/SearchableSelect.vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    offices: {
        type: Array,
        default: () => [],
    },
    docTypes: {
        type: Array,
        default: () => [],
    },
    classifications: {
        type: Array,
        default: () => [],
    },
    attachments: {
        type: Array,
        default: () => [],
    },
    staffConcerns: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close'])

const today = new Date()

const attachedFiles = ref([])
const fileInputKey = ref(0)
const attachmentError = ref('')
const staffDropdownOpen = ref(false)
const staffSearch = ref('')

const MAX_FILE_SIZE_MB = 500
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024

const normalizeId = (value) => {
    return String(value ?? '').trim()
}

const getStaffId = (staff) => {
    return staff?.ID
        ?? staff?.id
        ?? staff?.IDpersonnel
        ?? ''
}

const getStaffName = (staff) => {
    return String(
        staff?.name
        ?? staff?.personnel_name
        ?? staff?.fullname
        ?? staff?.full_name
        ?? ''
    ).trim()
}

const officeOptions = computed(() => {
    const seen = new Set()

    return (props.offices || [])
        .map((office) => {
            const id = office?.ID ?? office?.id ?? office?.IDoffice ?? ''
            const officeName = String(
                office?.officename
                ?? office?.office_name
                ?? office?.name
                ?? ''
            ).trim()
            const abbrev = String(office?.abbrev ?? '').trim()
            const idsucs = office?.IDsucs ?? office?.idsucs ?? null
            const displayName = abbrev ? `${officeName} (${abbrev})` : officeName

            return {
                ...office,
                ID: id,
                id,
                IDoffice: id,
                officename: officeName,
                office_name: officeName,
                name: officeName,
                display_name: displayName,
                label: displayName,
                abbrev,
                IDsucs: idsucs,
            }
        })
        .filter((office) => {
            const id = normalizeId(office.ID)
            const name = String(office.officename ?? '').trim()

            if (!id || !name || name === '-') {
                return false
            }

            if (seen.has(id)) {
                return false
            }

            seen.add(id)
            return true
        })
        .sort((a, b) => String(a.officename).localeCompare(String(b.officename)))
})

const officeOptionsKey = computed(() => {
    const firstId = officeOptions.value[0]?.ID ?? 'none'
    const lastId = officeOptions.value[officeOptions.value.length - 1]?.ID ?? 'none'

    return `offices-${officeOptions.value.length}-${firstId}-${lastId}`
})

const form = useForm({
    classification_id: '',
    type_id: '',
    entry_month: String(today.getMonth() + 1).padStart(2, '0'),
    entry_day: String(today.getDate()).padStart(2, '0'),
    entry_year: String(today.getFullYear()),
    to_office_id: '',
    to_name: '',
    from_office_id: '',
    from_name: '',
    subject: '',
    regarding: '',
    remarks: '',

    /*
     * Keep the original single-assignee field.
     * This is used when the user does not choose multiple assignment.
     */
    staff_concern_id: '',

    /*
     * This drives the multi-select dropdown.
     * One selected ID still uses the original single-assignee backend field.
     */
    staff_concern_ids: [],

    attachments: [],
})

const staffOptions = computed(() => {
    const seen = new Set()

    return (props.staffConcerns || [])
        .map((staff) => {
            const id = normalizeId(getStaffId(staff))
            const name = getStaffName(staff)

            return {
                ...staff,
                ID: id,
                id,
                IDpersonnel: id,
                name,
                personnel_name: name,
                fullname: name,
                full_name: name,
            }
        })
        .filter((staff) => {
            const id = normalizeId(staff.ID)
            const name = String(staff.name || '').trim()

            if (!id || !name || seen.has(id)) {
                return false
            }

            seen.add(id)
            return true
        })
        .sort((a, b) => String(a.name).localeCompare(String(b.name)))
})

const selectedStaffIds = computed(() => {
    const ids = Array.isArray(form.staff_concern_ids)
        ? form.staff_concern_ids
        : []

    return [...new Set(
        ids
            .map((id) => normalizeId(id))
            .filter(Boolean)
    )]
})

const isMultipleAssignment = computed(() => {
    return selectedStaffIds.value.length > 1
})

const selectedStaff = computed(() => {
    const selected = new Set(selectedStaffIds.value)

    return staffOptions.value.filter((staff) => {
        return selected.has(normalizeId(staff.ID))
    })
})

const filteredStaffOptions = computed(() => {
    const keyword = String(staffSearch.value || '').trim().toLowerCase()

    if (!keyword) {
        return staffOptions.value
    }

    return staffOptions.value.filter((staff) => {
        return String(staff.name || '').toLowerCase().includes(keyword)
    })
})

const staffSelectionLabel = computed(() => {
    if (selectedStaffIds.value.length === 0) {
        return 'Select staff concern...'
    }

    if (selectedStaffIds.value.length === 1) {
        return selectedStaff.value[0]?.name || '1 staff selected'
    }

    return `${selectedStaffIds.value.length} staff selected`
})

const isStaffSelected = (staffId) => {
    return selectedStaffIds.value.includes(normalizeId(staffId))
}

const toggleStaffSelection = (staffId) => {
    const normalizedStaffId = normalizeId(staffId)

    if (!normalizedStaffId) {
        return
    }

    form.clearErrors('staff_concern_id')
    form.clearErrors('staff_concern_ids')

    if (isStaffSelected(normalizedStaffId)) {
        form.staff_concern_ids = selectedStaffIds.value.filter((id) => {
            return id !== normalizedStaffId
        })
    } else {
        form.staff_concern_ids = [
            ...selectedStaffIds.value,
            normalizedStaffId,
        ]
    }

    /*
     * Keep the original single-assignee field synchronized.
     * It will be sent only when exactly one staff member is selected.
     */
    form.staff_concern_id = form.staff_concern_ids.length === 1
        ? form.staff_concern_ids[0]
        : ''
}

const removeSelectedStaff = (staffId) => {
    const normalizedStaffId = normalizeId(staffId)

    form.staff_concern_ids = selectedStaffIds.value.filter((id) => {
        return id !== normalizedStaffId
    })

    form.staff_concern_id = form.staff_concern_ids.length === 1
        ? form.staff_concern_ids[0]
        : ''
}

const clearSelectedStaff = () => {
    form.staff_concern_id = ''
    form.staff_concern_ids = []
    form.clearErrors('staff_concern_id')
    form.clearErrors('staff_concern_ids')
}

const numberToLetters = (number) => {
    let result = ''
    let current = number

    while (current > 0) {
        current -= 1
        result = String.fromCharCode(65 + (current % 26)) + result
        current = Math.floor(current / 26)
    }

    return result
}

const assignmentPreview = computed(() => {
    return selectedStaff.value.map((staff, index) => ({
        staff_id: normalizeId(staff.ID),
        staff_name: staff.name,
        reference: isMultipleAssignment.value
            ? `DTS-[Generated]-${numberToLetters(index + 1)}`
            : 'DTS-[Generated]',
    }))
})

const formatFileSize = (bytes) => {
    if (!bytes) return '0 KB'

    const kilobytes = bytes / 1024

    if (kilobytes < 1024) {
        return `${kilobytes.toFixed(1)} KB`
    }

    return `${(kilobytes / 1024).toFixed(2)} MB`
}

const isPdfFile = (file) => {
    return file?.type === 'application/pdf'
        || String(file?.name || '').toLowerCase().endsWith('.pdf')
}

const handleFileChange = (event) => {
    attachmentError.value = ''
    form.clearErrors('attachments')

    const files = Array.from(event.target.files || [])

    if (!files.length) {
        attachedFiles.value = []
        syncAttachmentsToForm()
        return
    }

    const file = files[0]

    if (!isPdfFile(file)) {
        attachedFiles.value = []
        syncAttachmentsToForm()
        attachmentError.value = 'PDF file only. Please select a PDF document.'
        fileInputKey.value += 1
        return
    }

    if (file.size > MAX_FILE_SIZE_BYTES) {
        attachedFiles.value = []
        syncAttachmentsToForm()
        attachmentError.value = `Maximum file size is ${MAX_FILE_SIZE_MB}MB per PDF.`
        fileInputKey.value += 1
        return
    }

    attachedFiles.value = [
        {
            temp_id: `${Date.now()}-${Math.random()}`,
            type_id: null,
            type_name: 'PDF Document',
            file,
            file_name: file.name,
            file_size: file.size,
        },
    ]

    syncAttachmentsToForm()
}

const syncAttachmentsToForm = () => {
    form.attachments = attachedFiles.value.map((item) => ({
        type_id: item.type_id,
        type_name: item.type_name,
        file: item.file,
    }))
}

const removeAttachment = (tempId) => {
    attachedFiles.value = attachedFiles.value.filter((item) => {
        return item.temp_id !== tempId
    })

    syncAttachmentsToForm()
}

const resetAttachmentFields = () => {
    attachedFiles.value = []
    form.attachments = []
    attachmentError.value = ''
    fileInputKey.value += 1
}

const resetStaffConcernFields = () => {
    form.staff_concern_id = ''
    form.staff_concern_ids = []
    staffDropdownOpen.value = false
    staffSearch.value = ''
}

const closeModal = () => {
    form.reset()
    form.clearErrors()
    resetAttachmentFields()
    resetStaffConcernFields()
    emit('close')
}

const validateRequiredFields = () => {
    form.clearErrors()
    attachmentError.value = ''

    const errors = {}

    if (!normalizeId(form.classification_id)) {
        errors.classification_id = 'Classification is required.'
    }

    if (!normalizeId(form.type_id)) {
        errors.type_id = 'Type is required.'
    }

    if (!normalizeId(form.entry_month)) {
        errors.entry_month = 'Entry month is required.'
    }

    if (!normalizeId(form.entry_day)) {
        errors.entry_day = 'Entry day is required.'
    }

    if (!normalizeId(form.entry_year)) {
        errors.entry_year = 'Entry year is required.'
    }

    if (!normalizeId(form.to_office_id)) {
        errors.to_office_id = 'To Office is required.'
    }

    if (!normalizeId(form.to_name)) {
        errors.to_name = 'To name is required.'
    }

    if (!normalizeId(form.from_office_id)) {
        errors.from_office_id = 'From Office is required.'
    }

    if (!normalizeId(form.from_name)) {
        errors.from_name = 'From name is required.'
    }

    if (!normalizeId(form.subject)) {
        errors.subject = 'Subject is required.'
    }

    if (selectedStaffIds.value.length === 0) {
        errors.staff_concern_ids = 'Select at least one Staff Concern.'
    }

    Object.entries(errors).forEach(([field, message]) => {
        form.setError(field, message)
    })

    if (Object.keys(errors).length > 0) {
        alert(Object.values(errors).join('\n'))
        return false
    }

    return true
}

const submitForm = () => {
    syncAttachmentsToForm()

    if (!validateRequiredFields()) {
        return
    }

    /*
     * SINGLE MODE:
     * Send the original staff_concern_id so the old backend process stays intact.
     *
     * MULTIPLE MODE:
     * Send staff_concern_ids and the multiple-assignment flag so the backend
     * can create the separate A/B/C workflow entries.
     */
    form.transform((data) => {
        if (selectedStaffIds.value.length === 1) {
            const {
                staff_concern_ids,
                ...singlePayload
            } = data

            return {
                ...singlePayload,
                staff_concern_id: selectedStaffIds.value[0],
                is_multiple_assignment: false,
            }
        }

        const {
            staff_concern_id,
            ...multiplePayload
        } = data

        return {
            ...multiplePayload,
            staff_concern_ids: selectedStaffIds.value,
            is_multiple_assignment: true,
        }
    })

    form.post('/dts/documents/store', {
        preserveScroll: true,
        forceFormData: true,

        onStart: () => {
            console.log('SUBMIT STARTED')
            console.log('FORM DATA:', form.data())
        },

        onSuccess: () => {
            console.log('SUBMIT SUCCESS')
            closeModal()
        },

        onError: (errors) => {
            console.log('SUBMIT ERRORS:', errors)

            const messages = Object.values(errors).join('\n')
            alert(messages || 'May validation error.')
        },

        onFinish: () => {
            console.log('SUBMIT FINISHED')
        },
    })
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 py-8"
    >
        <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-5">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">
                        Add Document
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Fill out the document details below.
                    </p>

                    <p class="mt-2 text-xs font-semibold text-slate-400">
                        A unique DTS No. will be assigned only after the document is successfully saved.
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100"
                    @click="closeModal"
                >
                    ✕
                </button>
            </div>

            <form class="space-y-6 p-6" @submit.prevent="submitForm">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-bold text-slate-700">
                            Classification<span class="text-red-600">*</span>
                        </label>

                        <SearchableSelect
                            v-model="form.classification_id"
                            :options="classifications"
                            placeholder="Search classification..."
                            :id-keys="['value', 'id', 'ID', 'IDclassification']"
                            :label-keys="['name', 'description', 'classification', 'title']"
                        />

                        <p
                            v-if="form.errors.classification_id"
                            class="mt-2 text-xs font-bold text-red-700"
                        >
                            {{ form.errors.classification_id }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-bold text-slate-700">
                            Type<span class="text-red-600">*</span>
                        </label>

                        <SearchableSelect
                            v-model="form.type_id"
                            :options="docTypes"
                            placeholder="Search type..."
                            :id-keys="['ID', 'id', 'IDtype']"
                            :label-keys="['description', 'name', 'doctype', 'title']"
                        />

                        <p
                            v-if="form.errors.type_id"
                            class="mt-2 text-xs font-bold text-red-700"
                        >
                            {{ form.errors.type_id }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-bold text-slate-700">
                            Entry Date<span class="text-red-600">*</span>
                            <span class="ml-1 text-xs font-semibold text-slate-500">(MM/DD/YYYY)</span>
                        </label>

                        <div class="grid grid-cols-3 gap-2">
                            <input
                                v-model="form.entry_month"
                                type="text"
                                required
                                maxlength="2"
                                placeholder="MM"
                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />

                            <input
                                v-model="form.entry_day"
                                type="text"
                                required
                                maxlength="2"
                                placeholder="DD"
                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />

                            <input
                                v-model="form.entry_year"
                                type="text"
                                required
                                maxlength="4"
                                placeholder="YYYY"
                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>

                        <p
                            v-if="form.errors.entry_month || form.errors.entry_day || form.errors.entry_year"
                            class="mt-2 text-xs font-bold text-red-700"
                        >
                            {{ form.errors.entry_month || form.errors.entry_day || form.errors.entry_year }}
                        </p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        To Office<span class="text-red-600">*</span>
                    </label>

                    <SearchableSelect
                        v-model="form.to_office_id"
                        :key="`to-${officeOptionsKey}`"
                        :options="officeOptions"
                        placeholder="Search office..."
                        :id-keys="['ID', 'id', 'IDoffice']"
                        :label-keys="['display_name', 'officename', 'office_name', 'name', 'label']"
                    />

                    <p
                        v-if="form.errors.to_office_id"
                        class="mt-2 text-xs font-bold text-red-700"
                    >
                        {{ form.errors.to_office_id }}
                    </p>

                    <label class="mb-1 mt-3 block text-sm font-bold text-slate-700">
                        To<span class="text-red-600">*</span>
                    </label>

                    <input
                        v-model="form.to_name"
                        type="text"
                        maxlength="255"
                        required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        placeholder="Enter recipient name"
                    />

                    <p
                        v-if="form.errors.to_name"
                        class="mt-2 text-xs font-bold text-red-700"
                    >
                        {{ form.errors.to_name }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        From Office<span class="text-red-600">*</span>
                    </label>

                    <SearchableSelect
                        v-model="form.from_office_id"
                        :key="`from-${officeOptionsKey}`"
                        :options="officeOptions"
                        placeholder="Search office..."
                        :id-keys="['ID', 'id', 'IDoffice']"
                        :label-keys="['display_name', 'officename', 'office_name', 'name', 'label']"
                    />

                    <p
                        v-if="form.errors.from_office_id"
                        class="mt-2 text-xs font-bold text-red-700"
                    >
                        {{ form.errors.from_office_id }}
                    </p>

                    <label class="mb-1 mt-3 block text-sm font-bold text-slate-700">
                        From<span class="text-red-600">*</span>
                    </label>

                    <input
                        v-model="form.from_name"
                        type="text"
                        maxlength="255"
                        required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        placeholder="Enter sender name"
                    />

                    <p
                        v-if="form.errors.from_name"
                        class="mt-2 text-xs font-bold text-red-700"
                    >
                        {{ form.errors.from_name }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        Subject<span class="text-red-600">*</span>
                    </label>

                    <textarea
                        v-model="form.subject"
                        rows="4"
                        required
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        placeholder="Enter subject"
                    ></textarea>

                    <p
                        v-if="form.errors.subject"
                        class="mt-2 text-xs font-bold text-red-700"
                    >
                        {{ form.errors.subject }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        Regarding
                    </label>

                    <textarea
                        v-model="form.regarding"
                        rows="3"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        placeholder="Enter regarding details"
                    ></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        Attachments <span class="text-xs font-semibold text-slate-500">(Optional)</span>
                    </label>

                    <div>
                        <input
                            :key="fileInputKey"
                            type="file"
                            accept="application/pdf,.pdf"
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            @change="handleFileChange"
                        />

                        <p class="mt-2 text-xs font-semibold text-slate-500">
                            Optional. PDF file only. Maximum 500MB per PDF document.
                        </p>

                        <p
                            v-if="attachmentError"
                            class="mt-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700"
                        >
                            {{ attachmentError }}
                        </p>
                    </div>

                    <div
                        v-if="attachedFiles.length > 0"
                        class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3"
                    >
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                            Attached Files
                        </p>

                        <div class="space-y-2">
                            <div
                                v-for="attachment in attachedFiles"
                                :key="attachment.temp_id"
                                class="grid grid-cols-1 gap-2 rounded-lg bg-white px-4 py-3 text-sm shadow-sm md:grid-cols-[1fr_auto]"
                            >
                                <div>
                                    <p class="font-bold text-slate-800">
                                        {{ attachment.type_name }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ attachment.file_name }} · {{ formatFileSize(attachment.file_size) }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="text-left text-xs font-bold text-red-600 hover:text-red-700 md:text-right"
                                    @click="removeAttachment(attachment.temp_id)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        Remarks
                    </label>

                    <textarea
                        v-model="form.remarks"
                        rows="3"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        placeholder="Enter remarks"
                    ></textarea>
                </div>

                <!-- Staff Concern: one multi-select dropdown; old process stays for one selection -->
                <div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <label class="mb-1 block text-sm font-bold text-slate-700">
                                Staff Concern<span class="text-red-600">*</span>
                            </label>

                            <p class="text-xs font-semibold text-slate-500">
                                Select one or more staff members from the same dropdown.
                            </p>
                        </div>

                        <span
                            v-if="selectedStaffIds.length > 0"
                            class="w-fit rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700"
                        >
                            {{ selectedStaffIds.length }}
                            {{ selectedStaffIds.length === 1 ? 'staff selected' : 'staff selected' }}
                        </span>
                    </div>

                    <div class="relative mt-3">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-xl border border-slate-300 bg-white px-4 py-3 text-left text-sm outline-none transition hover:border-blue-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            @click="staffDropdownOpen = !staffDropdownOpen"
                        >
                            <span
                                class="min-w-0 truncate"
                                :class="selectedStaffIds.length > 0 ? 'font-semibold text-slate-800' : 'text-slate-400'"
                            >
                                {{ staffSelectionLabel }}
                            </span>

                            <span
                                class="shrink-0 text-slate-400 transition"
                                :class="staffDropdownOpen ? 'rotate-180' : ''"
                            >
                                ▼
                            </span>
                        </button>

                        <div
                            v-if="staffDropdownOpen"
                            class="absolute left-0 right-0 z-30 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
                        >
                            <div class="border-b border-slate-200 p-3">
                                <input
                                    v-model="staffSearch"
                                    type="text"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    placeholder="Search staff concern..."
                                />
                            </div>

                            <div class="max-h-64 overflow-y-auto p-2">
                                <button
                                    v-for="staff in filteredStaffOptions"
                                    :key="staff.ID"
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm hover:bg-blue-50"
                                    @click="toggleStaffSelection(staff.ID)"
                                >
                                    <span
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded border text-xs font-black"
                                        :class="isStaffSelected(staff.ID)
                                            ? 'border-blue-600 bg-blue-600 text-white'
                                            : 'border-slate-300 bg-white text-transparent'"
                                    >
                                        ✓
                                    </span>

                                    <span class="font-semibold text-slate-700">
                                        {{ staff.name }}
                                    </span>
                                </button>

                                <p
                                    v-if="filteredStaffOptions.length === 0"
                                    class="px-3 py-5 text-center text-sm font-semibold text-slate-500"
                                >
                                    No staff found.
                                </p>
                            </div>

                            <div class="flex items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-3 py-2.5">
                                <button
                                    type="button"
                                    class="text-xs font-bold text-red-600 hover:text-red-700 disabled:opacity-40"
                                    :disabled="selectedStaffIds.length === 0"
                                    @click="clearSelectedStaff"
                                >
                                    Clear selection
                                </button>

                                <button
                                    type="button"
                                    class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700"
                                    @click="staffDropdownOpen = false"
                                >
                                    Done
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="selectedStaff.length > 0"
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <div
                            v-for="staff in selectedStaff"
                            :key="staff.ID"
                            class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-800"
                        >
                            <span>{{ staff.name }}</span>

                            <button
                                type="button"
                                class="rounded-full text-blue-500 hover:text-red-600"
                                :aria-label="`Remove ${staff.name}`"
                                @click="removeSelectedStaff(staff.ID)"
                            >
                                ✕
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="form.errors.staff_concern_id || form.errors.staff_concern_ids"
                        class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700"
                    >
                        {{ form.errors.staff_concern_id || form.errors.staff_concern_ids }}
                    </p>

                    <div
                        v-if="assignmentPreview.length > 0"
                        class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4"
                    >
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm font-bold text-slate-800">
                                Assignment Preview
                            </p>

                            <p class="text-xs font-semibold text-slate-500">
                                Final DTS number will be generated after saving.
                            </p>
                        </div>

                        <div class="mt-3 space-y-2">
                            <div
                                v-for="assignment in assignmentPreview"
                                :key="assignment.staff_id"
                                class="grid grid-cols-1 gap-1 rounded-lg bg-white px-4 py-3 sm:grid-cols-[180px_1fr] sm:items-center"
                            >
                                <span class="font-mono text-sm font-black text-blue-700">
                                    {{ assignment.reference }}
                                </span>

                                <span class="text-sm font-semibold text-slate-700">
                                    {{ assignment.staff_name }}
                                </span>
                            </div>
                        </div>

                       
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Submitting...' : 'Submit' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
