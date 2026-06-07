<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import DTSLayout from '@/Layouts/DTSLayout.vue'

defineOptions({
    layout: DTSLayout,
})


const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
    offices: {
        type: Array,
        default: () => [],
    },
    personnel: {
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

const canReceiveDts = computed(() => {
    return ['1', '2', '3', '4'].includes(userRights.value)
})

const activeTab = ref('details')

const showReceiveModal = ref(false)
const showForwardModal = ref(false)
const showReturnModal = ref(false)
const showActionHistoryModal = ref(false)
const showReattachPanel = ref(false)

const receiveForm = useForm({})

const forwardForm = useForm({
    IDpersonnel: '',
    remarks: '',
})

const returnForm = useForm({
    remarks: '',
})

const remarkForm = useForm({
    remarks: '',
})

const reattachForm = useForm({
    attachments: [],
    remarks: '',
})

const selectedReattachFiles = ref([])
const reattachFileInputKey = ref(0)
const reattachError = ref('')

const tabs = [
    {
        key: 'details',
        label: 'Details',
    },
    {
        key: 'status',
        label: 'Status',
    },
    {
        key: 'related',
        label: 'Related Docs',
    },
]

const statusFlags = reactive({
    acknowledgement_yes_no: props.document.status_flags?.acknowledgement_yes_no || false,
    acknowledgement_spl_action: props.document.status_flags?.acknowledgement_spl_action || false,

    distribution_yes_no: props.document.status_flags?.distribution_yes_no || false,
    distribution_spl_action: props.document.status_flags?.distribution_spl_action || false,

    comments_yes_no: props.document.status_flags?.comments_yes_no || false,
    comments_spl_action: props.document.status_flags?.comments_spl_action || false,

    edit_yes_no: props.document.status_flags?.edit_yes_no || false,
    edit_spl_action: props.document.status_flags?.edit_spl_action || false,

    evaluation_yes_no: props.document.status_flags?.evaluation_yes_no || false,
    evaluation_spl_action: props.document.status_flags?.evaluation_spl_action || false,

    action_yes_no: props.document.status_flags?.action_yes_no || false,
    action_spl_action: props.document.status_flags?.action_spl_action || false,
})

const statusRows = computed(() => {
    return [
        {
            key: 'acknowledgement',
            label: 'For acknowledgement',
            yesNoKey: 'acknowledgement_yes_no',
            splActionKey: 'acknowledgement_spl_action',
        },
        {
            key: 'distribution',
            label: 'For distribution',
            yesNoKey: 'distribution_yes_no',
            splActionKey: 'distribution_spl_action',
        },
        {
            key: 'comments',
            label: 'For comments',
            yesNoKey: 'comments_yes_no',
            splActionKey: 'comments_spl_action',
        },
        {
            key: 'edit',
            label: 'For edit',
            yesNoKey: 'edit_yes_no',
            splActionKey: 'edit_spl_action',
        },
        {
            key: 'evaluation',
            label: 'For evaluation',
            yesNoKey: 'evaluation_yes_no',
            splActionKey: 'evaluation_spl_action',
        },
        {
            key: 'action',
            label: 'For action',
            yesNoKey: 'action_yes_no',
            splActionKey: 'action_spl_action',
        },
    ]
})

const openReceiveModal = () => {
    if (!canReceiveDts.value) return

    showReceiveModal.value = true
}

const openForwardModal = () => {
    if (!canReceiveDts.value) return

    showForwardModal.value = true
}

const openReturnModal = () => {
    if (!canReceiveDts.value) return

    showReturnModal.value = true
}

const closeReceiveModal = () => {
    showReceiveModal.value = false
}

const closeForwardModal = () => {
    showForwardModal.value = false
    forwardForm.reset()
    forwardForm.clearErrors()
}

const closeReturnModal = () => {
    showReturnModal.value = false
    returnForm.reset()
    returnForm.clearErrors()
}

const documentId = computed(() => {
    return props.document?.IDdoc
        ?? props.document?.id
        ?? props.document?.ID
        ?? props.document?.doc_id
        ?? null
})

const receiveDocument = () => {
    if (!canReceiveDts.value || !documentId.value) return

    receiveForm.post(`/dts/${documentId.value}/receive`, {
        preserveScroll: true,
        onSuccess: () => {
            showReceiveModal.value = false
        },
    })
}

const forwardDocument = () => {
    if (!canReceiveDts.value || !documentId.value) return

    forwardForm.post(`/dts/${documentId.value}/forward`, {
        preserveScroll: true,
        onSuccess: () => {
            showForwardModal.value = false
            forwardForm.reset()
        },
    })
}

const returnDocument = () => {
    if (!canReceiveDts.value || !documentId.value) return

    returnForm.post(`/dts/${documentId.value}/return`, {
        preserveScroll: true,
        onSuccess: () => {
            showReturnModal.value = false
            returnForm.reset()
        },
    })
}

const addRemark = () => {
    if (!canManageDts.value || !documentId.value) return

    remarkForm.post(`/dts/${documentId.value}/remarks`, {
        preserveScroll: true,
        onSuccess: () => {
            remarkForm.reset()
        },
    })
}

const isPdfFile = (file) => {
    return file?.type === 'application/pdf'
        || String(file?.name || '').toLowerCase().endsWith('.pdf')
}

const handleReattachFileChange = (event) => {
    reattachError.value = ''
    reattachForm.clearErrors()

    const files = Array.from(event.target.files || [])

    if (!files.length) {
        selectedReattachFiles.value = []
        reattachForm.attachments = []
        return
    }

    const invalidFiles = files.filter((file) => !isPdfFile(file))

    if (invalidFiles.length > 0) {
        selectedReattachFiles.value = []
        reattachForm.attachments = []
        reattachFileInputKey.value += 1
        reattachError.value = 'PDF files only. Please select PDF document(s).'
        return
    }

    selectedReattachFiles.value = files
    reattachForm.attachments = files
}

const removeReattachFile = (index) => {
    reattachError.value = ''
    reattachForm.clearErrors()

    selectedReattachFiles.value.splice(index, 1)
    selectedReattachFiles.value = [...selectedReattachFiles.value]
    reattachForm.attachments = selectedReattachFiles.value

    if (!selectedReattachFiles.value.length) {
        reattachFileInputKey.value += 1
    }
}

const resetReattachForm = () => {
    selectedReattachFiles.value = []
    reattachFileInputKey.value += 1
    reattachError.value = ''
    reattachForm.reset()
    reattachForm.clearErrors()
}

const openReattachPanel = () => {
    if (!canReceiveDts.value) {
        return
    }

    showReattachPanel.value = true
}

const closeReattachPanel = () => {
    showReattachPanel.value = false
    resetReattachForm()
}

const reattachFiles = () => {
    if (!canReceiveDts.value) {
        return
    }

    reattachError.value = ''
    reattachForm.clearErrors()

    if (!documentId.value) {
        reattachError.value = 'Document ID not found. Please reload this page and try again.'
        return
    }

    if (!selectedReattachFiles.value.length) {
        reattachError.value = 'Please select at least one PDF file.'
        return
    }

    const invalidFiles = selectedReattachFiles.value.filter((file) => !isPdfFile(file))

    if (invalidFiles.length > 0) {
        reattachError.value = 'PDF files only. Please remove non-PDF files.'
        return
    }

    reattachForm.attachments = selectedReattachFiles.value

    reattachForm.post(`/dts/${documentId.value}/attachments`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            showReattachPanel.value = false
            resetReattachForm()
        },
        onError: () => {
            reattachError.value = 'Upload failed. Please check the selected PDF file(s) and try again.'
        },
    })
}

const statusClass = (status) => {
    const value = String(status || '').toLowerCase()

    if (value.includes('pulled')) {
        return 'bg-slate-100 text-slate-800 border border-slate-300'
    }

    if (value.includes('return')) {
        return 'bg-red-100 text-red-800 border border-red-300'
    }

    if (value.includes('received')) {
        return 'bg-emerald-100 text-emerald-800 border border-emerald-300'
    }

    if (value.includes('for receiving')) {
        return 'bg-amber-100 text-amber-900 border border-amber-300'
    }

    if (value.includes('pending 07')) {
        return 'bg-orange-100 text-orange-800 border border-orange-300'
    }

    if (value.includes('pending')) {
        return 'bg-yellow-100 text-yellow-900 border border-yellow-300'
    }

    if (value.includes('review') || value.includes('process')) {
        return 'bg-purple-100 text-purple-800 border border-purple-300'
    }

    if (value.includes('approved') || value.includes('completed') || value.includes('cleared')) {
        return 'bg-green-100 text-green-800 border border-green-300'
    }

    return 'bg-blue-100 text-blue-800 border border-blue-300'
}

const classificationLabel = computed(() => {
    if (props.document.classification_label) {
        return props.document.classification_label
    }

    if (props.document.classification === 'True') {
        return 'Outgoing'
    }

    if (props.document.classification === 'False') {
        return 'Incoming'
    }

    return props.document.classification || '-'
})

const documentNumber = computed(() => {
    return props.document.document_no || documentId.value || '-'
})

const relatedDocuments = computed(() => {
    return props.document.related_docs
        || props.document.relatedDocuments
        || []
})

const statusSummary = computed(() => {
    return props.document.status_summary || {}
})

const currentWorkflowStatus = computed(() => {
    return statusSummary.value.current_status || props.document.status || 'Pending'
})

const canReceiveCurrentDocument = computed(() => {
    return canReceiveDts.value && currentWorkflowStatus.value === 'For Receiving'
})

const canTransferCurrentDocument = computed(() => {
    const status = String(currentWorkflowStatus.value || '').toLowerCase()

    return canReceiveDts.value
        && !status.includes('pulled')
})

const canReturnCurrentDocument = computed(() => {
    const status = String(currentWorkflowStatus.value || '').toLowerCase()

    return canReceiveDts.value
        && !status.includes('returned')
        && !status.includes('pulled')
})

const normalizeId = (value) => {
    return String(value ?? '').trim()
}

const normalizeText = (value) => {
    return String(value ?? '').trim().toLowerCase()
}

const toTime = (value) => {
    if (!value) return null

    const parsed = new Date(String(value).replace(' ', 'T')).getTime()

    return Number.isNaN(parsed) ? null : parsed
}

const getHistoryDocumentId = (item) => {
    return item?.IDdoc
        ?? item?.iddoc
        ?? item?.id_doc
        ?? item?.doc_id
        ?? item?.document_id
        ?? item?.documentId
        ?? item?.document_no
        ?? item?.IDdocument
        ?? item?.document?.IDdoc
        ?? item?.document?.id
        ?? item?.document?.doc_id
        ?? null
}

const getHistoryDate = (item) => {
    return item?.date
        ?? item?.created_at
        ?? item?.updated_at
        ?? item?.distdate
        ?? item?.entrydate
        ?? null
}

const getHistorySearchText = (item) => {
    return normalizeText([
        item?.type,
        item?.title,
        item?.description,
        item?.remarks,
        item?.action,
        item?.status,
    ].filter(Boolean).join(' '))
}

const isTransferHistoryItem = (item) => {
    const text = getHistorySearchText(item)

    return text.includes('transfer')
        || text.includes('transferred')
        || text.includes('forward')
        || text.includes('forwarded')
}

const isCreatedHistoryItem = (item) => {
    const text = getHistorySearchText(item)

    return text.includes('created')
        || text.includes('create')
        || text.includes('encoded')
        || text.includes('new document')
        || text.includes('initial')
}

const isReceiveReturnPulledHistoryItem = (item) => {
    const text = getHistorySearchText(item)

    return text.includes('received')
        || text.includes('returned')
        || text.includes('pulled')
}

const isInitialTransferHistory = (item, historyList) => {
    if (!isTransferHistoryItem(item)) {
        return false
    }

    const itemTime = toTime(getHistoryDate(item))
    const entryTime = toTime(props.document.entrydate ?? props.document.created_at)

    const isCloseToEntryDate =
        itemTime !== null
        && entryTime !== null
        && Math.abs(itemTime - entryTime) <= 300000

    const hasReceivedReturnedOrPulled = historyList.some((historyItem) => {
        return isReceiveReturnPulledHistoryItem(historyItem)
    })

    const allHistoryStillInitial = historyList.every((historyItem) => {
        return isTransferHistoryItem(historyItem) || isCreatedHistoryItem(historyItem)
    })

    const currentStatus = normalizeText(currentWorkflowStatus.value)

    const documentStillNewOrForReceiving =
        currentStatus === ''
        || currentStatus.includes('pending')
        || currentStatus.includes('for receiving')

    return isCloseToEntryDate
        || (
            documentStillNewOrForReceiving
            && !hasReceivedReturnedOrPulled
            && allHistoryStillInitial
        )
}

const remarksHistory = computed(() => {
    const history = []

    ;(props.document.remarks_history || []).forEach((item) => {
        history.push({
            id: `saved-remark-${item.id}`,
            type: 'Remark',
            title: 'Added Remark',
            actor: item.created_by_name || (item.created_by ? `Account #${item.created_by}` : 'Unknown account'),
            office: item.created_by_name || (item.created_by ? `Account #${item.created_by}` : 'Unknown account'),
            date: item.created_at,
            remarks: item.remarks,
            created_by: item.created_by,
            created_time: toTime(item.created_at),
            files: [],
        })
    })

    ;(props.document.attachments || []).forEach((file) => {
        const isReattachedFile = file.type_name === 'Re-attached File'

        /*
         * Do not show initial uploaded files in Action History.
         * For a newly created document, attachments are part of Document Created.
         * Only show Re-attached File actions.
         */
        if (!isReattachedFile) {
            return
        }

        const fileTime = toTime(file.created_at)

        const matchingRemark = history.find((item) => {
            const sameUser = String(item.created_by || '') === String(file.uploaded_by || '')
            const hasTime = item.created_time !== null && fileTime !== null
            const closeTime = hasTime && Math.abs(item.created_time - fileTime) <= 10000

            return sameUser && closeTime
        })

        if (matchingRemark) {
            matchingRemark.type = 'Re-attached File'
            matchingRemark.title = 'Re-attached File with Remarks'
            matchingRemark.files.push(file)
            return
        }

        history.push({
            id: `reattached-file-${file.id}`,
            type: 'Re-attached File',
            title: 'Re-attached File',
            actor: file.uploaded_by_name || (file.uploaded_by ? `Account #${file.uploaded_by}` : 'Unknown account'),
            office: file.uploaded_by_name || (file.uploaded_by ? `Account #${file.uploaded_by}` : 'Unknown account'),
            date: file.created_at,
            remarks: `Re-attached file: ${file.original_name || file.stored_name || 'Uploaded file'}`,
            created_by: file.uploaded_by,
            created_time: fileTime,
            files: [file],
        })
    })

    return history
})

const isTrueValue = (value) => {
    return ['true', 'y', 'yes', '1'].includes(normalizeText(value))
}

const actionHistory = computed(() => {
    
    const currentDocumentId = normalizeId(documentId.value)

   
    const history = []

    if (props.document.entrydate) {
        history.push({
            id: `created-document-${currentDocumentId}`,
            IDdoc: currentDocumentId,
            type: 'New',
            title: 'Added Document',
            description: 'Document was encoded in the tracking system.',
            actor: props.document.created_by_name
                || props.document.created_by
                || props.document.encoded_by_name
                || 'System',
            office: props.document.from_office || '-',
            date: props.document.entrydate,
            remarks: props.document.remarks || null,
            files: [],
        })
    }

    const documentEntryTime = toTime(props.document.entrydate ?? props.document.created_at)

    const distributions = [...(props.document.distributions || [])].sort((a, b) => {
        return Number(a.IDdist || 0) - Number(b.IDdist || 0)
    })

    distributions.forEach((distribution) => {
        const distributionDocumentId = normalizeId(distribution.IDdoc)

        if (currentDocumentId !== '' && distributionDocumentId === '') {
            return
        }

        if (
            currentDocumentId !== ''
            && distributionDocumentId !== ''
            && distributionDocumentId !== currentDocumentId
        ) {
            return
        }

        const distributionTime = toTime(distribution.distdate)

        if (
            documentEntryTime !== null
            && distributionTime !== null
            && distributionTime < documentEntryTime
        ) {
            return
        }

        const isActualTransfer = !!distribution.IDparentdist

        if (distribution.distdate && isActualTransfer) {
            const targetPersonnel = distribution.target_personnel_name
                || distribution.target_personnel
                || distribution.to_personnel
                || ''

            const transferTarget = targetPersonnel
                ? `${targetPersonnel}${distribution.office ? ` — ${distribution.office}` : ''}`
                : (distribution.office || 'assigned office')

            history.push({
                id: `transferred-${currentDocumentId}-${distribution.IDdist}`,
                IDdoc: currentDocumentId,
                type: 'Transferred',
                title: 'Transferred Document',
                description: `Document was transferred to ${transferTarget}.`,
                actor: distribution.transferred_by_name
                    || distribution.transferred_by
                    || (distribution.IDuser ? `Account #${distribution.IDuser}` : 'System'),
                office: distribution.office || '-',
                target_personnel: targetPersonnel || null,
                date: distribution.distdate,
                remarks: distribution.remarks || null,
                files: [],
            })
        }

        if (distribution.confirmdate) {
            history.push({
                id: `received-${currentDocumentId}-${distribution.IDdist}`,
                IDdoc: currentDocumentId,
                type: 'Received',
                title: 'Received Document',
                description: 'Document was tagged as received.',
                actor: distribution.received_by_name
                    || distribution.received_by
                    || (distribution.confirmuser ? `Account #${distribution.confirmuser}` : 'System'),
                office: distribution.office || '-',
                date: distribution.confirmdate,
                remarks: distribution.remarks || null,
                files: [],
            })
        }

        if (isTrueValue(distribution.YNreturn) || distribution.returndate) {
            history.push({
                id: `returned-${currentDocumentId}-${distribution.IDdist}`,
                IDdoc: currentDocumentId,
                type: 'Returned',
                title: 'Returned Document',
                description: 'Document was returned.',
                actor: distribution.transferred_by_name
                    || distribution.transferred_by
                    || (distribution.IDuser ? `Account #${distribution.IDuser}` : 'System'),
                office: distribution.office || '-',
                date: distribution.returndate || distribution.distdate,
                remarks: distribution.remarks || null,
                files: [],
            })
        }

        if (isTrueValue(distribution.YNpulled)) {
            history.push({
                id: `pulled-${currentDocumentId}-${distribution.IDdist}`,
                IDdoc: currentDocumentId,
                type: 'Pulled Out',
                title: 'Pulled Out Document',
                description: 'Document transfer was pulled out.',
                actor: distribution.transferred_by_name
                    || distribution.transferred_by
                    || (distribution.IDuser ? `Account #${distribution.IDuser}` : 'System'),
                office: distribution.office || '-',
                date: distribution.distdate,
                remarks: distribution.remarks || null,
                files: [],
            })
        }
    })

    const mergedHistory = [
        ...history,
        ...remarksHistory.value.map((item) => ({
            ...item,
            IDdoc: currentDocumentId,
        })),
    ]

    return mergedHistory.sort((a, b) => {
        const firstDate = toTime(getHistoryDate(a))
        const secondDate = toTime(getHistoryDate(b))

        if (firstDate === null || secondDate === null) {
            return 0
        }

        return secondDate - firstDate
    })
})

const historyTypeClass = (type) => {
    const value = String(type || '').toLowerCase()

    if (value.includes('created')) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800'
    }

    if (value.includes('received')) {
        return 'border-green-200 bg-green-50 text-green-800'
    }

    if (value.includes('transfer') || value.includes('forward')) {
        return 'border-blue-200 bg-blue-50 text-blue-800'
    }

    if (value.includes('returned')) {
        return 'border-red-200 bg-red-50 text-red-800'
    }

    if (value.includes('pulled')) {
        return 'border-slate-200 bg-slate-50 text-slate-800'
    }

    if (value.includes('remark')) {
        return 'border-amber-200 bg-amber-50 text-amber-800'
    }

    if (value.includes('attached') || value.includes('file')) {
        return 'border-purple-200 bg-purple-50 text-purple-800'
    }

    return 'border-blue-200 bg-blue-50 text-blue-800'
}

const historyDotClass = (type) => {
    const value = String(type || '').toLowerCase()

    if (value.includes('created')) return 'bg-emerald-600'
    if (value.includes('received')) return 'bg-green-600'
    if (value.includes('transfer') || value.includes('forward')) return 'bg-blue-600'
    if (value.includes('returned')) return 'bg-red-600'
    if (value.includes('pulled')) return 'bg-slate-600'
    if (value.includes('remark')) return 'bg-amber-500'
    if (value.includes('attached') || value.includes('file')) return 'bg-purple-600'

    return 'bg-blue-600'
}

const formatDateTime = (value) => {
    if (!value) {
        return '-'
    }

    const normalized = String(value).replace(' ', 'T')
    const date = new Date(normalized)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    }).format(date)
}

const formatFileSize = (bytes) => {
    if (!bytes) {
        return ''
    }

    const kilobytes = Number(bytes) / 1024

    if (kilobytes < 1024) {
        return `${kilobytes.toFixed(1)} KB`
    }

    return `${(kilobytes / 1024).toFixed(2)} MB`
}
</script>

<template>
    <Head title="Document Details" />

    <div class="min-h-screen bg-blue-50">
        <!-- Header -->
        <div class="border-b border-blue-200 bg-white">
            <div class="mx-auto flex max-w-screen-2xl items-center justify-between px-6 py-3">
                <div>
                    <h1 class="text-2xl font-black text-black">
                        Document Details
                    </h1>

                    <p class="text-sm font-semibold text-black">
                        View document information, routing status, and remarks history.
                    </p>

                </div>

                <Link
                    href="/dts"
                    class="rounded-lg border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50"
                >
                    Back to Homepage
                </Link>
            </div>
        </div>

        <main class="mx-auto max-w-screen-2xl px-6 py-5">
            <!-- Top Summary Card -->
            <div class="rounded-2xl border border-blue-200 bg-white shadow-sm">
                <div class="border-b border-blue-200 p-5">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-base font-black text-blue-700">
                                Document ID: {{ documentNumber }}
                            </p>

                            <span
                                class="inline-flex rounded-full px-4 py-1.5 text-sm font-black"
                                :class="statusClass(currentWorkflowStatus)"
                            >
                                {{ currentWorkflowStatus || 'No status' }}
                            </span>
                        </div>

                        <h2 class="mt-3 break-words text-3xl font-black leading-tight text-black">
                            {{ document.subject || 'No subject' }}
                        </h2>

                        <p class="mt-2 max-w-6xl break-words text-base font-semibold leading-7 text-black">
                            {{ document.regarding || 'No regarding details' }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-xl bg-blue-600 px-5 py-3 text-base font-black text-white hover:bg-blue-700"
                                @click="showActionHistoryModal = true"
                            >
                                View Action History
                            </button>

                            <button
                                v-if="canTransferCurrentDocument"
                                type="button"
                                class="rounded-xl bg-purple-600 px-5 py-3 text-base font-black text-white hover:bg-purple-700 disabled:opacity-60"
                                :disabled="forwardForm.processing"
                                @click="openForwardModal"
                            >
                                Transfer Document
                            </button>

                            <button
                                v-if="canReturnCurrentDocument"
                                type="button"
                                class="rounded-xl bg-red-600 px-5 py-3 text-base font-black text-white hover:bg-red-700 disabled:opacity-60"
                                :disabled="returnForm.processing"
                                @click="openReturnModal"
                            >
                                Return Document
                            </button>

                            <button
                                v-if="canReceiveCurrentDocument"
                                type="button"
                                class="rounded-xl bg-green-600 px-5 py-3 text-base font-black text-white hover:bg-green-700 disabled:opacity-60"
                                :disabled="receiveForm.processing"
                                @click="receiveDocument"
                            >
                                {{ receiveForm.processing ? 'Receiving...' : 'Receive Document' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Quick Info -->
                <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2 xl:grid-cols-5">
                    <div>
                        <p class="text-sm font-black uppercase tracking-wide text-blue-700">
                            Classification
                        </p>

                        <p class="mt-2 text-base font-black text-black">
                            {{ classificationLabel }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-black uppercase tracking-wide text-blue-700">
                            Document Type
                        </p>

                        <p class="mt-2 text-base font-black text-black">
                            {{ document.doctype || '-' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-black uppercase tracking-wide text-blue-700">
                            Entry Date
                        </p>

                        <p class="mt-2 text-base font-black text-black">
                            {{ formatDateTime(document.entrydate) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-black uppercase tracking-wide text-blue-700">
                            To
                        </p>

                        <p class="mt-2 break-words text-base font-black text-black">
                            {{ document.for_office || '-' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-black uppercase tracking-wide text-blue-700">
                            From
                        </p>

                        <p class="mt-2 break-words text-base font-black text-black">
                            {{ document.from_office || '-' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Details Content -->
            <div class="mt-5 rounded-2xl border border-blue-200 bg-white shadow-sm">
                <div class="p-5">
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                        <div class="space-y-4 xl:col-span-12">
                            <!-- Staff Concern -->
                            <div>
                                <p class="text-sm font-black uppercase tracking-wide text-blue-700">
                                    Staff Concern
                                </p>

                                <div class="mt-2 rounded-xl border border-blue-100 bg-blue-50 p-5 text-base font-black leading-7 text-black">
                                    {{ document.staff_concern || '-' }}
                                </div>
                            </div>

                            <!-- Attachments -->
                            <div>
                                <div class="rounded-2xl border-2 border-blue-500 bg-blue-50 p-4">
                                    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-base font-black uppercase tracking-wide text-blue-800">
                                                Attachments
                                            </p>

                                            <p class="text-sm font-semibold text-black">
                                                Uploaded files connected to this document.
                                            </p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex w-fit rounded-full bg-blue-700 px-3 py-1 text-xs font-bold text-white">
                                                {{ document.attachments?.length || 0 }} file(s)
                                            </span>

                                            <button
                                                v-if="canReceiveDts"
                                                type="button"
                                                class="inline-flex w-fit rounded-lg bg-emerald-600 px-4 py-2 text-xs font-black text-white hover:bg-emerald-700"
                                                @click="openReattachPanel"
                                            >
                                                + Re-attach File
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-if="document.attachments && document.attachments.length"
                                        class="space-y-2"
                                    >
                                        <div
                                            v-for="file in document.attachments"
                                            :key="file.id"
                                            class="rounded-xl border border-blue-300 bg-white p-3 shadow-sm"
                                        >
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="min-w-0">
                                                    <p class="break-words text-base font-black text-black">
                                                        {{ file.type_name || 'Attachment' }}
                                                    </p>

                                                    <p class="mt-1 break-words text-base font-bold text-blue-700">
                                                        {{ file.original_name || file.stored_name || 'Uploaded file' }}
                                                    </p>

                                                    <p
                                                        v-if="file.size"
                                                        class="mt-1 text-sm font-bold text-black"
                                                    >
                                                        Size: {{ formatFileSize(file.size) }}
                                                    </p>
                                                </div>

                                                <div class="flex shrink-0 flex-wrap gap-2">
                                                    <a
                                                        v-if="file.url"
                                                        :href="file.url"
                                                        target="_blank"
                                                        class="inline-flex justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700"
                                                    >
                                                        View File
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-else
                                        class="rounded-xl border border-dashed border-blue-400 bg-white p-4 text-center text-sm font-bold text-black"
                                    >
                                        No attachments uploaded.
                                    </div>

                                    <div
                                        v-if="canReceiveDts && showReattachPanel"
                                        class="mt-4 rounded-xl border border-emerald-200 bg-white p-4 shadow-sm"
                                    >
                                        <div class="mb-3">
                                            <p class="text-base font-black uppercase tracking-wide text-emerald-700">
                                                Re-attach New File
                                            </p>

                                            <p class="text-sm font-semibold text-black">
                                                Select PDF file(s) and add optional remarks for the new uploaded attachment.
                                            </p>
                                        </div>

                                        <input
                                            :key="reattachFileInputKey"
                                            type="file"
                                            accept="application/pdf,.pdf"
                                            multiple
                                            class="block w-full cursor-pointer rounded-xl border border-blue-300 bg-blue-50 text-sm font-semibold text-black file:mr-4 file:border-0 file:bg-blue-600 file:px-4 file:py-3 file:text-sm file:font-bold file:text-white hover:file:bg-blue-700"
                                            @change="handleReattachFileChange"
                                        />

                                        <p class="mt-2 text-xs font-semibold text-black">
                                            PDF files only. The upload URL will use Document ID: {{ documentId || 'Not found' }}.
                                        </p>

                                        <p
                                            v-if="reattachError || reattachForm.errors.attachments || reattachForm.errors['attachments.0']"
                                            class="mt-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700"
                                        >
                                            {{ reattachError || reattachForm.errors.attachments || reattachForm.errors['attachments.0'] }}
                                        </p>

                                        <div
                                            v-if="selectedReattachFiles.length"
                                            class="mt-3 space-y-2"
                                        >
                                            <div
                                                v-for="(file, index) in selectedReattachFiles"
                                                :key="`${file.name}-${index}`"
                                                class="flex flex-col gap-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div class="min-w-0">
                                                    <p class="break-words text-base font-black text-black">
                                                        {{ file.name }}
                                                    </p>

                                                    <p class="text-xs font-semibold text-blue-700">
                                                        {{ formatFileSize(file.size) }}
                                                    </p>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="shrink-0 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-50"
                                                    @click="removeReattachFile(index)"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                        </div>

                                        <textarea
                                            v-model="reattachForm.remarks"
                                            rows="2"
                                            class="mt-3 w-full rounded-xl border border-blue-300 bg-white px-4 py-3 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            placeholder="Optional remarks for this re-attached file..."
                                        ></textarea>

                                        <p
                                            v-if="reattachForm.errors.remarks"
                                            class="mt-1 text-xs font-bold text-red-700"
                                        >
                                            {{ reattachForm.errors.remarks }}
                                        </p>

                                        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:justify-end">
                                            <button
                                                type="button"
                                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                                                :disabled="reattachForm.processing"
                                                @click="closeReattachPanel"
                                            >
                                                Cancel
                                            </button>

                                            <button
                                                type="button"
                                                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                                :disabled="reattachForm.processing || !selectedReattachFiles.length"
                                                @click="reattachFiles"
                                            >
                                                {{ reattachForm.processing ? 'Uploading...' : 'Upload Re-attached File' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Multiple Remarks -->
                            <div v-if="canReceiveDts">
                                <p class="text-sm font-black uppercase tracking-wide text-blue-700">
                                    Add Remarks
                                </p>

                                <div class="mt-2 rounded-xl border border-blue-100 bg-blue-50 p-4">
                                    <textarea
                                        v-model="remarkForm.remarks"
                                        rows="3"
                                        class="w-full rounded-xl border border-blue-300 bg-white px-4 py-3 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        placeholder="Type new remarks here..."
                                    ></textarea>

                                    <p
                                        v-if="remarkForm.errors.remarks"
                                        class="mt-1 text-xs font-bold text-red-700"
                                    >
                                        {{ remarkForm.errors.remarks }}
                                    </p>

                                    <div class="mt-3 flex justify-end">
                                        <button
                                            v-if="canReceiveDts"
                                            type="button"
                                            class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-60"
                                            :disabled="remarkForm.processing"
                                            @click="addRemark"
                                        >
                                            {{ remarkForm.processing ? 'Saving...' : '+ Add Remark' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Transfer Document Modal -->
    <div
        v-if="showForwardModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8"
    >
        <div class="w-full max-w-xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
            <div class="border-b border-blue-100 bg-indigo-600 px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-indigo-100">
                            Transfer
                        </p>

                        <h2 class="mt-2 text-2xl font-black">
                            Transfer Document
                        </h2>

                        <p class="mt-1 text-sm font-semibold text-indigo-100">
                            Select the personnel who will receive this document.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl bg-white/15 px-4 py-2 text-sm font-black text-white hover:bg-white/25"
                        @click="closeForwardModal"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div>
                    <label class="text-sm font-black uppercase tracking-wide text-blue-700">
                        Transfer To Personnel
                    </label>

                    <select
                        v-model="forwardForm.IDpersonnel"
                        class="mt-2 w-full rounded-xl border border-blue-300 bg-white px-4 py-3 text-base font-bold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">
                            Select personnel
                        </option>

                        <option
                            v-for="person in personnel"
                            :key="person.ID"
                            :value="person.ID"
                        >
                            {{ person.name }}{{ person.office_name ? ` — ${person.office_name}` : '' }}
                        </option>
                    </select>

                    <p
                        v-if="forwardForm.errors.IDpersonnel"
                        class="mt-2 text-sm font-bold text-red-700"
                    >
                        {{ forwardForm.errors.IDpersonnel }}
                    </p>
                </div>

                <div class="mt-5">
                    <label class="text-sm font-black uppercase tracking-wide text-blue-700">
                        Remarks
                    </label>

                    <textarea
                        v-model="forwardForm.remarks"
                        rows="4"
                        class="mt-2 w-full rounded-xl border border-blue-300 bg-white px-4 py-3 text-base font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        placeholder="Optional transfer remarks..."
                    ></textarea>

                    <p
                        v-if="forwardForm.errors.remarks"
                        class="mt-2 text-sm font-bold text-red-700"
                    >
                        {{ forwardForm.errors.remarks }}
                    </p>
                </div>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-base font-black text-slate-700 hover:bg-slate-50"
                        :disabled="forwardForm.processing"
                        @click="closeForwardModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-5 py-3 text-base font-black text-white hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="forwardForm.processing"
                        @click="forwardDocument"
                    >
                        {{ forwardForm.processing ? 'Transferring...' : 'Confirm Transfer' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Return Document Modal -->
    <div
        v-if="showReturnModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8"
    >
        <div class="w-full max-w-xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
            <div class="border-b border-red-100 bg-red-600 px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-red-100">
                            Return
                        </p>

                        <h2 class="mt-2 text-2xl font-black">
                            Return Document
                        </h2>

                        <p class="mt-1 text-sm font-semibold text-red-100">
                            Add a reason before tagging this document as returned.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl bg-white/15 px-4 py-2 text-sm font-black text-white hover:bg-white/25"
                        @click="closeReturnModal"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="p-6">
                <label class="text-sm font-black uppercase tracking-wide text-red-700">
                    Return Remarks <span class="text-red-600">*</span>
                </label>

                <textarea
                    v-model="returnForm.remarks"
                    rows="5"
                    class="mt-2 w-full rounded-xl border border-red-300 bg-white px-4 py-3 text-base font-semibold text-black outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
                    placeholder="Type reason for returning this document..."
                ></textarea>

                <p
                    v-if="returnForm.errors.remarks"
                    class="mt-2 text-sm font-bold text-red-700"
                >
                    {{ returnForm.errors.remarks }}
                </p>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-base font-black text-slate-700 hover:bg-slate-50"
                        :disabled="returnForm.processing"
                        @click="closeReturnModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-xl bg-red-600 px-5 py-3 text-base font-black text-white hover:bg-red-700 disabled:opacity-60"
                        :disabled="returnForm.processing"
                        @click="returnDocument"
                    >
                        {{ returnForm.processing ? 'Returning...' : 'Confirm Return' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Action History Modal -->
    <div
        v-if="showActionHistoryModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8"
    >
        <div class="max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
            <div class="border-b border-blue-100 bg-blue-600 px-6 py-5 text-white">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                            Audit Trail
                        </p>

                        <h2 class="mt-2 text-2xl font-black">
                            Document Action History
                        </h2>

                        <p class="mt-1 text-sm font-semibold text-blue-100">
                            Full tracking history for Document ID: {{ documentNumber }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl bg-white/15 px-4 py-2 text-sm font-black text-white hover:bg-white/25"
                        @click="showActionHistoryModal = false"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="max-h-[70vh] overflow-y-auto p-6">
                <div
                    v-if="actionHistory.length"
                    class="relative space-y-4"
                >
                    <div
                        v-for="(item, index) in actionHistory"
                        :key="item.id || `${item.type}-${index}`"
                        class="relative rounded-2xl border p-4"
                        :class="historyTypeClass(item.type)"
                    >
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex h-3 w-3 rounded-full"
                                        :class="historyDotClass(item.type)"
                                    ></span>

                                    <p class="text-base font-black text-slate-900">
                                        {{ item.title }}
                                    </p>

                                    <span class="rounded-full bg-white/80 px-3 py-1 text-xs font-black uppercase tracking-wide">
                                        {{ item.type }}
                                    </span>
                                </div>

                                <p class="mt-2 text-sm font-semibold leading-6 text-slate-800">
                                    {{ item.description || '-' }}
                                </p>

                                <div class="mt-3 grid grid-cols-1 gap-2 text-xs font-bold text-slate-700 md:grid-cols-3">
                                    <p>
                                        <span class="text-slate-900">Actor:</span>
                                        {{ item.actor || '-' }}
                                    </p>

                                    <p>
                                        <span class="text-slate-900">Office:</span>
                                        {{ item.office || '-' }}
                                    </p>

                                    <p v-if="item.target_personnel">
                                        <span class="text-slate-900">Transferred To:</span>
                                        {{ item.target_personnel }}
                                    </p>

                                    <p>
                                        <span class="text-slate-900">Date/Time:</span>
                                        {{ formatDateTime(getHistoryDate(item)) }}
                                    </p>
                                </div>

                                <p
                                    v-if="item.remarks"
                                    class="mt-3 whitespace-pre-line rounded-xl bg-white/80 px-4 py-3 text-sm font-semibold leading-6 text-slate-800"
                                >
                                    {{ item.remarks }}
                                </p>

                                <div
                                    v-if="item.files && item.files.length"
                                    class="mt-3 space-y-2"
                                >
                                    <a
                                        v-for="file in item.files"
                                        :key="file.id"
                                        :href="file.url"
                                        target="_blank"
                                        class="inline-flex w-full items-center justify-between gap-3 rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-bold text-blue-700 hover:bg-blue-50"
                                    >
                                        <span class="min-w-0 break-words">
                                            {{ file.original_name || file.stored_name || 'View attached file' }}
                                        </span>

                                        <span class="shrink-0">
                                            View File
                                        </span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-blue-300 p-8 text-center"
                >
                    <p class="text-lg font-black text-slate-900">
                        No action history found
                    </p>

                    <p class="mt-2 text-sm font-semibold text-slate-600">
                        No transfer, receive, remarks, or file actions have been recorded for this document yet.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>