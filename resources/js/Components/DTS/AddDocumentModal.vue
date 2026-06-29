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
    nextDocumentId: {
        type: [Number, String],
        default: null,
    },
})

const emit = defineEmits(['close'])

const today = new Date()

const attachedFiles = ref([])
const fileInputKey = ref(0)
const attachmentError = ref('')

const MAX_FILE_SIZE_MB = 500
const MAX_FILE_SIZE_BYTES = MAX_FILE_SIZE_MB * 1024 * 1024


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
            const id = String(office.ID ?? '').trim()
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
    staff_concern_id: '',
    attachments: [],
})

const nextDocumentIdDisplay = computed(() => {
    const value = String(props.nextDocumentId ?? '').trim()

    return value ? `DTS - ${value}` : 'Generating...'
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

    /*
     * Only one attachment is needed for Add Document.
     * Replace the current file instead of adding multiple files.
     */
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

const closeModal = () => {
    form.reset()
    form.clearErrors()
    resetAttachmentFields()
    emit('close')
}

const validateRequiredFields = () => {
    form.clearErrors()
    attachmentError.value = ''

    const errors = {}

    if (!String(form.classification_id || '').trim()) {
        errors.classification_id = 'Classification is required.'
    }

    if (!String(form.type_id || '').trim()) {
        errors.type_id = 'Type is required.'
    }

    if (!String(form.entry_month || '').trim()) {
        errors.entry_month = 'Entry month is required.'
    }

    if (!String(form.entry_day || '').trim()) {
        errors.entry_day = 'Entry day is required.'
    }

    if (!String(form.entry_year || '').trim()) {
        errors.entry_year = 'Entry year is required.'
    }

    if (!String(form.to_office_id || '').trim()) {
        errors.to_office_id = 'To Office is required.'
    }

    if (!String(form.to_name || '').trim()) {
        errors.to_name = 'To name is required.'
    }

    if (!String(form.from_office_id || '').trim()) {
        errors.from_office_id = 'From Office is required.'
    }

    if (!String(form.from_name || '').trim()) {
        errors.from_name = 'From name is required.'
    }

    if (!String(form.subject || '').trim()) {
        errors.subject = 'Subject is required.'
    }

    if (!String(form.staff_concern_id || '').trim()) {
        errors.staff_concern_id = 'Staff Concern is required.'
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
    /*
     * Attachments are optional during Add Document.
     * If no file is selected, form.attachments will be an empty array.
     * The user can attach PDF files later in the Show/Details page.
     */
    syncAttachmentsToForm()

    if (!validateRequiredFields()) {
        return
    }

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

                    <div class="mt-3 inline-flex items-center gap-2 rounded-full border border-green-200 bg-green-50 px-3 py-1.5">
                        <span class="text-xs font-black uppercase tracking-[0.16em] text-green-700">
                            Doc ID
                        </span>

                        <span class="text-sm font-black text-green-900">
                            {{ nextDocumentIdDisplay }}
                        </span>
                    </div>
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

                    <p
                        v-else
                        class="mt-2 text-xs text-slate-500"
                    >
                        No files attached yet. You can still save this document and attach files later from the document details page.
                    </p>
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

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        Staff Concern<span class="text-red-600">*</span>
                    </label>

                    <SearchableSelect
                        v-model="form.staff_concern_id"
                        :options="staffConcerns"
                        placeholder="Search staff concern..."
                        :id-keys="['ID', 'id', 'IDpersonnel']"
                        :label-keys="['name', 'personnel_name', 'fullname']"
                    />

                    <p
                        v-if="form.errors.staff_concern_id"
                        class="mt-2 text-xs font-bold text-red-700"
                    >
                        {{ form.errors.staff_concern_id }}
                    </p>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="closeModal">
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