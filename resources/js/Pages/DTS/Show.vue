<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
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
    actionTypes: {
        type: Array,
        default: () => [],
    },
    isSuperAdminViewOnly: {
        type: Boolean,
        default: false,
    },
    canReceiveDts: {
        type: Boolean,
        default: false,
    },
    canReattachDts: {
        type: Boolean,
        default: false,
    },
    canRemarkDts: {
        type: Boolean,
        default: false,
    },
    canActionTakenDts: {
        type: Boolean,
        default: false,
    },
})

const page = usePage()

const userRights = computed(() => {
    return String(page.props.auth?.user?.rights ?? '').trim()
})

const isSuperAdminViewOnly = computed(() => {
    return Boolean(props.isSuperAdminViewOnly)
})

const canManageDts = computed(() => {
    return !isSuperAdminViewOnly.value && ['1', '3'].includes(userRights.value)
})

const canReceiveDts = computed(() => {
    /*
     * This permission comes from the controller.
     * Staff users may view documents that are not tagged to them,
     * but they must not receive/transfer/return/action taken unless
     * the document is actually tagged to their personnel record.
     */
    return !isSuperAdminViewOnly.value && Boolean(props.canReceiveDts)
})

const canReattachDts = computed(() => {
    /*
     * Re-attach is maintained separately.
     * Staff users can still re-attach files even when the document is not tagged
     * to them, as long as the controller allows their role to manage attachments.
     */
    return !isSuperAdminViewOnly.value && Boolean(props.canReattachDts)
})

const canRemarkDts = computed(() => {
    
    return Boolean(props.canRemarkDts)
})

const isDocumentReceivedForAction = computed(() => {
    const status = String(currentWorkflowStatus.value || '').toLowerCase()

    return status.includes('received')
        || status.includes('for action')
        || status.includes('action taken')
})

const canActionTakenDts = computed(() => {
    return !isSuperAdminViewOnly.value
        && Boolean(props.canActionTakenDts)
        && isDocumentReceivedForAction.value
})

const activeTab = ref('details')

const showReceiveModal = ref(false)
const showForwardModal = ref(false)
const showReturnModal = ref(false)
const showActionTakenModal = ref(false)
const showActionHistoryModal = ref(false)
const showReattachPanel = ref(false)

const receiveForm = useForm({})

const forwardForm = useForm({
    IDpersonnel: '',
    remarks: '',
})

const actionTakenForm = useForm({
    IDactionType: '',
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

const showActionTakenReceiveWarning = ref(false)

const openActionTakenModal = () => {
    if (!canActionTakenDts.value) return

    showActionTakenReceiveWarning.value = false
    actionTakenForm.reset()
    actionTakenForm.clearErrors()
    showActionTakenModal.value = true
}

const handleActionTakenClick = () => {
    if (actionTakenForm.processing) return

    if (!isDocumentReceivedForAction.value) {
        showActionTakenReceiveWarning.value = true
        return
    }

    openActionTakenModal()
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

const closeActionTakenModal = () => {
    if (actionTakenForm.processing) return

    showActionTakenModal.value = false
    actionTakenForm.reset()
    actionTakenForm.clearErrors()
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

            /*
             * Reload the page after receiving so the workflow status updates.
             * Once the document is received, the Select Action button will appear.
             */
            router.reload({
                preserveScroll: true,
            })
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

const actionTakenDocument = () => {
    if (!canActionTakenDts.value || !documentId.value) return

    actionTakenForm.post(`/dts/${documentId.value}/action-taken`, {
        preserveScroll: true,
        onSuccess: () => {
            showActionTakenModal.value = false
            actionTakenForm.reset()
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
    if (!canRemarkDts.value || !documentId.value) return

    remarkForm.post(`/dts/${documentId.value}/remarks`, {
        preserveScroll: true,
        preserveState: true,
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
    if (!canReattachDts.value) {
        return
    }

    showReattachPanel.value = true
}

const closeReattachPanel = () => {
    showReattachPanel.value = false
    resetReattachForm()
}

const reattachFiles = () => {
    if (!canReattachDts.value) {
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

const getFirstFilled = (...values) => {
    for (const value of values) {
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            return value
        }
    }

    return '-'
}

const hasFilledValue = (value) => {
    return value !== undefined
        && value !== null
        && String(value).trim() !== ''
        && String(value).trim() !== '-'
}

const documentSubject = computed(() => {
    return getFirstFilled(
        props.document.subject,
        props.document.Subject,
        props.document.title,
        props.document.document_subject
    )
})

const regardingDisplay = computed(() => {
    return getFirstFilled(
        props.document.regarding,
        props.document.re,
        props.document.regarding_details,
        props.document.details
    )
})

const documentTypeDisplay = computed(() => {
    return getFirstFilled(
        props.document.doctype,
        props.document.document_type,
        props.document.document_type_name,
        props.document.doctype_description,
        props.document.type,
        props.document.type_name,
        props.document.IDdoctype ? `Doc Type #${props.document.IDdoctype}` : null
    )
})

const entryDateDisplay = computed(() => {
    return getFirstFilled(
        props.document.entrydate,
        props.document.entry_date,
        props.document.date_encoded,
        props.document.created_at
    )
})

const toOfficeDisplay = computed(() => {
    return getFirstFilled(
        props.document.for_office,
        props.document.to_office,
        props.document.recipient_office,
        props.document.office_to,
        props.document.IDfor ? `Office #${props.document.IDfor}` : null
    )
})

const fromOfficeDisplay = computed(() => {
    return getFirstFilled(
        props.document.from_office,
        props.document.sender_office,
        props.document.office_from,
        props.document.IDfrom ? `Office #${props.document.IDfrom}` : null
    )
})

const toNameDisplay = computed(() => {
    return getFirstFilled(
        props.document.to_name,
        props.document.recipient_name,
        props.document.addressee_name,
        props.document.name_to,
        props.document.to_person_name
    )
})

const fromNameDisplay = computed(() => {
    return getFirstFilled(
        props.document.from_name,
        props.document.sender_name,
        props.document.source_name,
        props.document.name_from,
        props.document.from_person_name
    )
})

const staffConcernDisplay = computed(() => {
    return getFirstFilled(
        props.document.staff_concern,
        props.document.staff_concern_name,
        props.document.keeper_name,
        props.document.assigned_personnel,
        props.document.personnel_name,
        props.document.IDkeeper ? `Personnel #${props.document.IDkeeper}` : null
    )
})

const getOriginalDocumentRemarks = () => {
    return getFirstFilled(
        props.document.initial_remarks,
        props.document.original_remarks,
        props.document.document_initial_remarks,
        props.document.encoded_remarks,
        props.document.added_document_remarks,
        props.document.document_remarks,
        props.document.remarks,
        props.document.distribution_remarks
    )
}

/*
 * Added Document remarks must stay as the original remarks from document creation.
 * This should not change when the user adds a new remark.
 */
const addedDocumentRemarksSnapshot = ref(getOriginalDocumentRemarks())

const addedDocumentRemarksDisplay = computed(() => {
    return addedDocumentRemarksSnapshot.value
})

/*
 * Current Remarks card should show the latest remark added to dts_document_remarks.
 * If there is no new remark yet, it falls back to the original document remarks.
 */
const currentRemarksDisplay = computed(() => {
    const latestRemark = [...(props.document.remarks_history || [])]
        .filter((item) => hasFilledValue(item?.remarks))
        .sort((a, b) => {
            const firstDate = toTime(a?.created_at || a?.updated_at)
            const secondDate = toTime(b?.created_at || b?.updated_at)

            if (firstDate === null && secondDate === null) return 0
            if (firstDate === null) return 1
            if (secondDate === null) return -1

            return secondDate - firstDate
        })[0]

    return getFirstFilled(
        latestRemark?.remarks,
        props.document.current_remarks,
        addedDocumentRemarksDisplay.value
    )
})

// Keep old variable name only for compatibility with existing references.
const initialRemarksDisplay = computed(() => {
    return addedDocumentRemarksDisplay.value
})

const classificationLabel = computed(() => {
    const classification = getFirstFilled(
        props.document.classification_label,
        props.document.classification,
        props.document.document_classification
    )

    const value = String(classification || '').trim().toLowerCase()

    if (['true', '1', 'outgoing'].includes(value)) {
        return 'Outgoing'
    }

    if (['false', '0', 'incoming'].includes(value)) {
        return 'Incoming'
    }

    return classification || '-'
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
        const actionType = normalizeText(item.action_type || 'remark')
        const isActionTaken = actionType === 'action_taken'
        const actionName = item.action_label || item.action_name || ''
        const actionDescription = item.action_description || ''
        const actionTarget = actionName || ''

        history.push({
            id: `${isActionTaken ? 'action-taken' : 'saved-remark'}-${item.id}`,
            type: isActionTaken ? 'Select Action' : 'Remark',
            title: isActionTaken ? 'Select Action' : 'Added Remark',
            description: isActionTaken
                ? `Selected action: ${actionTarget || 'Action'}.`
                : 'A remark was added to this document.',
            actor: item.created_by_name || (item.created_by ? `Account #${item.created_by}` : 'Unknown account'),
            office: isActionTaken
                ? '-'
                : (item.created_by_name || (item.created_by ? `Account #${item.created_by}` : 'Unknown account')),
            target_action: isActionTaken ? actionTarget : null,
            target_personnel: null,
            action_name: actionName,
            action_description: actionDescription,
            date: item.created_at,
            remarks: item.remarks,
            action_type: actionType,
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

    if (hasFilledValue(entryDateDisplay.value)) {
        history.push({
            id: `created-document-${currentDocumentId}`,
            IDdoc: currentDocumentId,
            type: 'New',
            title: 'Added Document',
            description: 'Document was added in the tracking system.',
            actor: props.document.created_by_name
                || props.document.created_by
                || props.document.encoded_by_name
                || 'System',
            office: fromOfficeDisplay.value || '-',
            date: entryDateDisplay.value,
            remarks: hasFilledValue(addedDocumentRemarksDisplay.value) ? addedDocumentRemarksDisplay.value : null,
            files: [],
        })
    }

    const documentEntryTime = toTime(entryDateDisplay.value ?? props.document.created_at)

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

    if (value.includes('action')) {
        return 'border-cyan-200 bg-cyan-50 text-cyan-800'
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
    if (value.includes('action')) return 'bg-cyan-600'
    if (value.includes('returned')) return 'bg-red-600'
    if (value.includes('pulled')) return 'bg-slate-600'
    if (value.includes('remark')) return 'bg-amber-500'
    if (value.includes('attached') || value.includes('file')) return 'bg-purple-600'

    return 'bg-blue-600'
}

const historyTargetLabel = (item) => {
    return normalizeText(item?.type).includes('action') ? 'Action:' : 'Transferred To:'
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

    <div class="min-h-screen bg-slate-100">
        <!-- Header -->
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-screen-2xl flex-col gap-3 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.24em] text-blue-600">
                        Document Tracking System
                    </p>

                    <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-950">
                        Document Details
                    </h1>

                    <p class="mt-1 text-sm font-semibold text-slate-500">
                        Compact document workspace with actions, files, and remarks.
                    </p>
                </div>

                <Link
                    href="/dts"
                    class="inline-flex w-fit items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-black text-slate-700 shadow-sm hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"
                >
                    ← Back to Homepage
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-screen-2xl px-6 py-5">
            <!-- Compact Hero -->
            <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <div class="grid grid-cols-1 lg:grid-cols-[1fr_330px]">
                    <div class="p-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-blue-600 px-4 py-1.5 text-xs font-black uppercase tracking-wide text-white">
                                DTS - #{{ documentNumber }}
                            </span>

                            <span
                                class="rounded-full px-4 py-1.5 text-xs font-black uppercase tracking-wide"
                                :class="statusClass(currentWorkflowStatus)"
                            >
                                {{ currentWorkflowStatus || 'No status' }}
                            </span>

                            <span
                                v-if="isSuperAdminViewOnly"
                                class="rounded-full bg-amber-100 px-4 py-1.5 text-xs font-black uppercase tracking-wide text-amber-800"
                            >
                                View Only
                            </span>
                        </div>

                        <h2 class="mt-4 max-w-5xl break-words text-2xl font-black leading-tight text-slate-950 lg:text-3xl">
                            {{ hasFilledValue(documentSubject) ? documentSubject : 'No subject' }}
                        </h2>

                        <p class="mt-3 max-w-6xl whitespace-pre-line break-words text-sm font-semibold leading-7 text-slate-600">
                            {{ hasFilledValue(regardingDisplay) ? regardingDisplay : 'No regarding details' }}
                        </p>
                    </div>

                    <!-- Action Panel -->
                    <div class="border-t border-slate-200 bg-blue-600 p-5 text-white lg:border-l lg:border-t-0">
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                            Quick Actions
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-2">
                            <button
                                v-if="!isSuperAdminViewOnly && canReceiveCurrentDocument"
                                type="button"
                                class="rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-black text-white shadow-sm hover:bg-emerald-600 disabled:opacity-60"
                                :disabled="receiveForm.processing"
                                @click="receiveDocument"
                            >
                                {{ receiveForm.processing ? 'Receiving...' : 'Receive Document' }}
                            </button>

                            <button
                                v-if="canActionTakenDts"
                                type="button"
                                class="rounded-xl bg-cyan-500 px-5 py-2.5 text-sm font-black text-white shadow-sm hover:bg-cyan-600 disabled:opacity-60"
                                :disabled="actionTakenForm.processing"
                                @click="openActionTakenModal"
                            >
                                Select Action
                            </button>

                            <button
                                v-if="!isSuperAdminViewOnly && canTransferCurrentDocument"
                                type="button"
                                class="rounded-xl bg-indigo-500 px-5 py-2.5 text-sm font-black text-white shadow-sm hover:bg-indigo-600 disabled:opacity-60"
                                :disabled="forwardForm.processing"
                                @click="openForwardModal"
                            >
                                Transfer Document
                            </button>

                            <button
                                v-if="!isSuperAdminViewOnly && canReturnCurrentDocument"
                                type="button"
                                class="rounded-xl bg-rose-500 px-5 py-2.5 text-sm font-black text-white shadow-sm hover:bg-rose-600 disabled:opacity-60"
                                :disabled="returnForm.processing"
                                @click="openReturnModal"
                            >
                                Return Document
                            </button>

                            <button
                                type="button"
                                class="mt-2 rounded-xl bg-white px-5 py-2.5 text-sm font-black text-blue-700 shadow-sm hover:bg-blue-50"
                                @click="showActionHistoryModal = true"
                            >
                                View Action History
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Info Cards -->
            <section class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-blue-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">
                        From
                    </p>

                    <p class="mt-2 break-words text-sm font-black leading-6 text-slate-950">
                        {{ fromOfficeDisplay }}
                    </p>

                    <p
                        v-if="hasFilledValue(fromNameDisplay)"
                        class="mt-1 break-words text-xs font-bold leading-5 text-slate-500"
                    >
                        Name: {{ fromNameDisplay }}
                    </p>
                </div>

                <div class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-600">
                        To
                    </p>

                    <p class="mt-2 break-words text-sm font-black leading-6 text-slate-950">
                        {{ toOfficeDisplay }}
                    </p>

                    <p
                        v-if="hasFilledValue(toNameDisplay)"
                        class="mt-1 break-words text-xs font-bold leading-5 text-slate-500"
                    >
                        Name: {{ toNameDisplay }}
                    </p>
                </div>

                <div class="rounded-2xl border border-purple-100 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-purple-600">
                        Type
                    </p>

                    <p class="mt-2 break-words text-sm font-black leading-6 text-slate-950">
                        {{ documentTypeDisplay }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">
                        Entry Date
                    </p>

                    <p class="mt-2 break-words text-sm font-black leading-6 text-slate-950">
                        {{ formatDateTime(entryDateDisplay) }}
                    </p>
                </div>
            </section>

            <!-- Main Layout -->
            <section class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_390px]">
                <!-- LEFT: main details + remarks -->
                <div class="space-y-5">
                    <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-600">
                                    Document Information
                                </p>

                                <h3 class="mt-1 text-xl font-black text-slate-950">
                                    Summary Details
                                </h3>
                            </div>

                          
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">
                                    Classification
                                </p>

                                <p class="mt-2 text-sm font-black text-slate-950">
                                    {{ classificationLabel }}
                                </p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">
                                    Current Status
                                </p>

                                <p class="mt-2 text-sm font-black text-slate-950">
                                    {{ currentWorkflowStatus || 'No status' }}
                                </p>
                            </div>
                        </div>

                        <!-- Staff Concern + Current Remarks only: compact height -->
                        <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
                            <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-700">
                                        Staff Concern
                                    </p>

                                    <span class="rounded-full bg-white px-3 py-1 text-[11px] font-black text-blue-700">
                                        Assigned
                                    </span>
                                </div>

                                <p class="mt-2 break-words text-sm font-black leading-6 text-slate-950">
                                    {{ staffConcernDisplay }}
                                </p>
                            </div>

                            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs font-black uppercase tracking-[0.18em] text-indigo-700">
                                        Current Remarks
                                    </p>

                                    <span class="rounded-full bg-white px-3 py-1 text-[11px] font-black text-indigo-700">
                                        Latest
                                    </span>
                                </div>

                                <p class="mt-2 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-800">
                                    {{ hasFilledValue(currentRemarksDisplay) ? currentRemarksDisplay : 'No current remarks.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Add Remarks below Staff Concern and Current Remarks -->
                        <div class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">
                                        Add Remarks
                                    </p>

                                    <p class="mt-1 text-xs font-semibold text-emerald-700">
                                        Add new remarks for this document.
                                    </p>
                                </div>

                                <span class="rounded-full bg-white px-3 py-1 text-[11px] font-black text-emerald-700">
                                    New Note
                                </span>
                            </div>

                            <template v-if="canRemarkDts">
                                <textarea
                                    v-model="remarkForm.remarks"
                                    rows="3"
                                    class="w-full resize-none rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                    placeholder="Type new remarks here..."
                                ></textarea>

                                <p
                                    v-if="remarkForm.errors.remarks"
                                    class="mt-2 text-xs font-bold text-red-700"
                                >
                                    {{ remarkForm.errors.remarks }}
                                </p>

                                <div class="mt-3 flex justify-end">
                                    <button
                                        type="button"
                                        class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                        :disabled="remarkForm.processing"
                                        @click="addRemark"
                                    >
                                        {{ remarkForm.processing ? 'Saving...' : '+ Add Remark' }}
                                    </button>
                                </div>
                            </template>

                            <div
                                v-else
                                class="rounded-xl border border-dashed border-emerald-200 bg-white px-4 py-6 text-center"
                            >
                                <p class="text-sm font-black text-slate-600">
                                    Remarks action is not available for this access level.
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- RIGHT: Attachments only -->
                <aside class="space-y-5">
                    <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-600">
                                    Files
                                </p>

                                <h3 class="mt-1 text-lg font-black text-slate-950">
                                    Attachments
                                </h3>
                            </div>

                            <span class="rounded-full bg-blue-50 px-3 py-1.5 text-xs font-black text-blue-700">
                                {{ document.attachments?.length || 0 }}
                            </span>
                        </div>

                        <button
                            v-if="canReattachDts"
                            type="button"
                            class="mb-4 w-full rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-black text-white hover:bg-emerald-700"
                            @click="openReattachPanel"
                        >
                            + Re-attach File
                        </button>

                        <div
                            v-if="document.attachments && document.attachments.length"
                            class="max-h-[520px] space-y-3 overflow-y-auto pr-1"
                        >
                            <article
                                v-for="file in document.attachments"
                                :key="file.id"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <p class="text-xs font-black uppercase tracking-wide text-blue-600">
                                    {{ file.type_name || 'Attachment' }}
                                </p>

                                <p class="mt-2 break-words text-sm font-black text-slate-950">
                                    {{ file.original_name || file.stored_name || 'Uploaded file' }}
                                </p>

                                <p v-if="file.size" class="mt-1 text-xs font-bold text-slate-500">
                                    Size: {{ formatFileSize(file.size) }}
                                </p>

                                <a
                                    v-if="file.url"
                                    :href="file.url"
                                    target="_blank"
                                    class="mt-3 inline-flex rounded-xl bg-blue-600 px-4 py-2 text-xs font-black text-white hover:bg-blue-700"
                                >
                                    View File
                                </a>
                            </article>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-7 text-center"
                        >
                            <p class="text-sm font-black text-slate-600">
                                No attachments uploaded.
                            </p>
                        </div>

                        <div
                            v-if="canReattachDts && showReattachPanel"
                            class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4"
                        >
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-black text-emerald-800">
                                        Re-attach New File
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-emerald-700">
                                        PDF files only.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="rounded-lg border border-emerald-200 bg-white px-3 py-1.5 text-xs font-black text-emerald-700"
                                    @click="closeReattachPanel"
                                >
                                    Close
                                </button>
                            </div>

                            <input
                                :key="reattachFileInputKey"
                                type="file"
                                accept="application/pdf,.pdf"
                                multiple
                                class="block w-full cursor-pointer rounded-xl border border-emerald-200 bg-white text-xs font-semibold text-slate-800 file:mr-3 file:border-0 file:bg-emerald-600 file:px-3 file:py-2.5 file:text-xs file:font-bold file:text-white hover:file:bg-emerald-700"
                                @change="handleReattachFileChange"
                            />

                            <p
                                v-if="reattachError || reattachForm.errors.attachments || reattachForm.errors['attachments.0']"
                                class="mt-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-bold text-red-700"
                            >
                                {{ reattachError || reattachForm.errors.attachments || reattachForm.errors['attachments.0'] }}
                            </p>

                            <div v-if="selectedReattachFiles.length" class="mt-3 space-y-2">
                                <div
                                    v-for="(file, index) in selectedReattachFiles"
                                    :key="`${file.name}-${index}`"
                                    class="rounded-xl border border-emerald-100 bg-white p-3"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="break-words text-xs font-black text-slate-900">
                                                {{ file.name }}
                                            </p>
                                            <p class="text-[11px] font-semibold text-emerald-700">
                                                {{ formatFileSize(file.size) }}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="shrink-0 rounded-lg border border-red-200 bg-white px-2.5 py-1 text-[11px] font-bold text-red-700 hover:bg-red-50"
                                            @click="removeReattachFile(index)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <textarea
                                v-model="reattachForm.remarks"
                                rows="3"
                                class="mt-3 w-full rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                placeholder="Optional remarks..."
                            ></textarea>

                            <div class="mt-3 grid grid-cols-1 gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"
                                    :disabled="reattachForm.processing || !selectedReattachFiles.length"
                                    @click="reattachFiles"
                                >
                                    {{ reattachForm.processing ? 'Uploading...' : 'Upload File' }}
                                </button>

                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                                    :disabled="reattachForm.processing"
                                    @click="closeReattachPanel"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </section>
                </aside>
            </section>
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


    <!-- Select Action Modal -->
    <div
        v-if="showActionTakenModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8"
    >
        <div class="w-full max-w-xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
            <div class="border-b border-cyan-100 bg-cyan-600 px-6 py-5 text-white">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.22em] text-cyan-100">
                            Action Taken
                        </p>

                        <h2 class="mt-2 text-2xl font-black">
                            Select Action
                        </h2>

                        <p class="mt-1 text-sm font-semibold text-cyan-100">
                            Select the appropriate action for this document.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl bg-white/15 px-4 py-2 text-sm font-black text-white hover:bg-white/25"
                        @click="closeActionTakenModal"
                    >
                        Close
                    </button>
                </div>
            </div>

            <div class="p-6">
                <div>
                    <label class="text-sm font-black uppercase tracking-wide text-cyan-700">
                        Action <span class="text-red-600">*</span>
                    </label>

                    <select
                        v-model="actionTakenForm.IDactionType"
                        class="mt-2 w-full rounded-xl border border-cyan-300 bg-white px-4 py-3 text-base font-bold text-black outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
                    >
                        <option value="">
                            Select action
                        </option>

                        <option
                            v-for="actionType in actionTypes"
                            :key="`action-type-${actionType.id}`"
                            :value="actionType.id"
                        >
                            {{ actionType.name }}
                        </option>
                    </select>

                    <p
                        v-if="actionTakenForm.errors.IDactionType"
                        class="mt-2 text-sm font-bold text-red-700"
                    >
                        {{ actionTakenForm.errors.IDactionType }}
                    </p>

                    <p
                        v-if="actionTakenForm.errors.action"
                        class="mt-2 text-sm font-bold text-red-700"
                    >
                        {{ actionTakenForm.errors.action }}
                    </p>
                </div>

                <div class="mt-5">
                    <label class="text-sm font-black uppercase tracking-wide text-cyan-700">
                        Remarks
                    </label>

                    <textarea
                        v-model="actionTakenForm.remarks"
                        rows="4"
                        class="mt-2 w-full rounded-xl border border-cyan-300 bg-white px-4 py-3 text-base font-semibold text-black outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
                        placeholder="Optional action remarks."
                    ></textarea>

                    <p
                        v-if="actionTakenForm.errors.remarks"
                        class="mt-2 text-sm font-bold text-red-700"
                    >
                        {{ actionTakenForm.errors.remarks }}
                    </p>
                </div>

               

                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-base font-black text-slate-700 hover:bg-slate-50"
                        :disabled="actionTakenForm.processing"
                        @click="closeActionTakenModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-xl bg-cyan-600 px-5 py-3 text-base font-black text-white hover:bg-cyan-700 disabled:opacity-60"
                        :disabled="actionTakenForm.processing || !actionTakenForm.IDactionType"
                        @click="actionTakenDocument"
                    >
                        {{ actionTakenForm.processing ? 'Saving...' : 'Confirm' }}
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
                            Full tracking history for DTS - #{{ documentNumber }}
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

                                    <p v-if="item.target_personnel || item.target_action">
                                        <span class="text-slate-900">{{ historyTargetLabel(item) }}</span>
                                        {{ item.target_action || item.target_personnel }}
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
