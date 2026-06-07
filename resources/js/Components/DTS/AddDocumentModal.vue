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
    from_office_id: '',
    subject: '',
    regarding: '',
    remarks: '',
    staff_concern_id: '',
    attachments: [],
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

    const files = Array.from(event.target.files || [])

    if (!files.length) {
        return
    }

    const invalidFiles = files.filter((file) => !isPdfFile(file))

    if (invalidFiles.length > 0) {
        attachmentError.value = 'PDF files only. Please remove non-PDF files and try again.'
        fileInputKey.value += 1
        return
    }

    files.forEach((file) => {
        attachedFiles.value.push({
            temp_id: `${Date.now()}-${Math.random()}`,
            type_id: null,
            type_name: 'PDF Document',
            file,
            file_name: file.name,
            file_size: file.size,
        })
    })

    syncAttachmentsToForm()

    fileInputKey.value += 1
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

const submitForm = () => {
    syncAttachmentsToForm()

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
                                maxlength="2"
                                placeholder="MM"
                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />

                            <input
                                v-model="form.entry_day"
                                type="text"
                                maxlength="2"
                                placeholder="DD"
                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />

                            <input
                                v-model="form.entry_year"
                                type="text"
                                maxlength="4"
                                placeholder="YYYY"
                                class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        To<span class="text-red-600">*</span>
                    </label>

                    <div class="grid gap-2" style="grid-template-columns: minmax(0, 1fr) auto;">
                        <SearchableSelect
                            v-model="form.to_office_id"
                            :key="`to-${officeOptionsKey}`"
                            :options="officeOptions"
                            placeholder="Search office..."
                            :id-keys="['ID', 'id', 'IDoffice']"
                            :label-keys="['display_name', 'officename', 'office_name', 'name', 'label']"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        From<span class="text-red-600">*</span>
                    </label>

                    <div class="grid gap-2" style="grid-template-columns: minmax(0, 1fr) auto;">
                        <SearchableSelect
                            v-model="form.from_office_id"
                            :key="`from-${officeOptionsKey}`"
                            :options="officeOptions"
                            placeholder="Search office..."
                            :id-keys="['ID', 'id', 'IDoffice']"
                            :label-keys="['display_name', 'officename', 'office_name', 'name', 'label']"
                        />
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-bold text-slate-700">
                        Subject<span class="text-red-600">*</span>
                    </label>

                    <textarea
                        v-model="form.subject"
                        rows="4"
                        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        placeholder="Enter subject"
                    ></textarea>
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
                        Attachments
                    </label>

                    <div>
                        <input
                            :key="fileInputKey"
                            type="file"
                            accept="application/pdf,.pdf"
                            multiple
                            class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            @change="handleFileChange"
                        />

                        <p class="mt-2 text-xs font-semibold text-slate-500">
                            PDF files only. You may select one or more PDF documents.
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
                        No files attached yet.
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