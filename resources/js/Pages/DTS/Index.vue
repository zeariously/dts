<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import DTSLayout from '@/Layouts/DTSLayout.vue'
import AddDocumentModal from '@/Components/DTS/AddDocumentModal.vue'

const props = defineProps({
    documents: {
        type: [Object, Array],
        default: () => ({
            data: [],
            links: [],
            from: 0,
            to: 0,
            total: 0,
            current_page: 1,
            last_page: 1,
        }),
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            for_receiving: 0,
            received: 0,
            addressed: 0,
            returned: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            per_page: 10,
            year: '',
        }),
    },
    years: {
        type: Array,
        default: () => [],
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
    viewerNotifications: {
        type: Array,
        default: () => [],
    },
    creatorReceivedNotifications: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()

const userRights = computed(() => {
    return String(page.props.auth?.user?.rights ?? '').trim()
})

const canManageDts = computed(() => {
    // Role 3 only has manage/staff actions in this page.
    // Role 1 and Role 4 should behave like Role 2 here.
    return ['3'].includes(userRights.value)
})

const canReceiveDts = computed(() => {
    // Roles 1, 2, and 4 have the same document receiving/viewing actions.
    // Role 3 can also receive because it is the manage/staff role.
    return ['1', '2', '3', '4'].includes(userRights.value)
})

const isViewerAccount = computed(() => {
    // Auto notification popup applies to viewer-style roles.
    return ['1', '2', '4'].includes(userRights.value)
})
const flashSuccess = computed(() => {
    return page.props.flash?.success || ''
})

const flashError = computed(() => {
    return page.props.flash?.error || ''
})

const firstErrorMessage = computed(() => {
    const errors = page.props.errors || {}

    const firstKey = Object.keys(errors)[0]

    if (!firstKey) {
        return ''
    }

    const message = errors[firstKey]

    if (Array.isArray(message)) {
        return message[0] || ''
    }

    return message || ''
})


const showTransferNotificationModal = ref(false)
const seenNotificationKeys = ref([])

const notificationStorageKey = computed(() => {
    const userId = page.props.auth?.user?.ID || page.props.auth?.user?.id || 'guest'

    return `dts_seen_notifications_${userId}`
})

const loadSeenNotificationKeys = () => {
    if (typeof window === 'undefined') return

    try {
        const stored = window.localStorage.getItem(notificationStorageKey.value)

        seenNotificationKeys.value = stored ? JSON.parse(stored) : []
    } catch (error) {
        seenNotificationKeys.value = []
    }
}

const saveSeenNotificationKeys = () => {
    if (typeof window === 'undefined') return

    window.localStorage.setItem(notificationStorageKey.value, JSON.stringify(seenNotificationKeys.value))
}

const notificationKey = (item) => {
    return [
        item.notification_type || 'for_receiving',
        item.IDdoc || item.document_no || '',
        item.received_date || item.transfer_date || item.due_date || '',
    ].join(':')
}

const markNotificationSeen = (item) => {
    const key = notificationKey(item)

    if (!key || seenNotificationKeys.value.includes(key)) return

    seenNotificationKeys.value = [...seenNotificationKeys.value, key]
    saveSeenNotificationKeys()
}

onMounted(() => {
    loadSeenNotificationKeys()
})

const transferNotifications = computed(() => {
    const forReceiving = (props.viewerNotifications || []).map((item) => ({
        ...item,
        notification_type: item.notification_type || 'for_receiving',
    }))

    const receivedByAddressee = (props.creatorReceivedNotifications || []).map((item) => ({
        ...item,
        notification_type: item.notification_type || 'received_by_addressee',
    }))

    return [...forReceiving, ...receivedByAddressee].filter((item) => {
        return !seenNotificationKeys.value.includes(notificationKey(item))
    })
})

const hasTransferNotifications = computed(() => {
    return transferNotifications.value.length > 0
})

const transferNotificationCount = computed(() => {
    return transferNotifications.value.length
})

const addressedCount = computed(() => {
    return props.stats.addressed
        ?? props.stats.address
        ?? props.stats.action_taken
        ?? props.stats.for_action
        ?? 0
})

const forReceivingNotifications = computed(() => {
    return transferNotifications.value.filter((item) => item.notification_type !== 'received_by_addressee')
})

const overdueTransferNotifications = computed(() => {
    return forReceivingNotifications.value.filter((item) => item.is_overdue)
})

const openNotificationsFromBell = () => {
    // Do not open the notification modal when there are no notifications.
    // This prevents Role 2 from seeing the popup every time the bell is clicked.
    if (!hasTransferNotifications.value) {
        showTransferNotificationModal.value = false
        return
    }

    showTransferNotificationModal.value = true
}

const closeTransferNotificationModal = () => {
    showTransferNotificationModal.value = false
}

watch(
    transferNotifications,
    (items) => {
        /*
         * Notifications should no longer auto-prompt as a popup.
         * They will stay inside the notification bell only.
         */
        if (!items.length) {
            showTransferNotificationModal.value = false
        }
    },
    { immediate: true }
)



const search = ref(props.filters?.search || '')
const perPage = ref(Number(props.filters?.per_page || 10))

let searchTimer = null
let skipNextSearchWatch = false

const showAddDocumentModal = ref(false)
const showEditEntryDateModal = ref(false)
const selectedDocument = ref(null)

const showPendingActionModal = ref(false)
const selectedPendingDocument = ref(null)
const pendingActionType = ref('')
const pendingActionProcessing = ref(false)

const entryDateForm = useForm({
    entrydate: '',
})

const currentParams = computed(() => {
    const queryString = page.url.includes('?') ? page.url.split('?')[1] : ''
    return new URLSearchParams(queryString)
})

const activeSection = computed(() => {
    return currentParams.value.get('section') || 'documents'
})

const isAllDocumentsSection = computed(() => {
    return activeSection.value === 'all-documents'
})

const isAddressedDocumentsSection = computed(() => {
    return activeSection.value === 'addressed-docs'
})

const activeFilter = computed(() => {
    return currentParams.value.get('filter') || ''
})

const tableSearchPlaceholder = computed(() => {
    if (activeSection.value === 'received-docs') {
        return 'Search '
    }

    if (activeSection.value === 'pending-docs' || activeSection.value === 'pending-docs-07') {
        return 'Search '
    }

    if (activeSection.value === 'sent-docs') {
        return 'Search '
    }

    if (activeSection.value === 'pulled-out-docs') {
        return 'Search '
    }

    if (activeFilter.value === 'for-receiving') {
        return 'Search'
    }

    if (['received', 'collab-received'].includes(activeFilter.value)) {
        return 'Search '
    }

    if (activeFilter.value === 'for-action') {
        return 'Search '
    }

    if (activeFilter.value === 'returned') {
        return 'Search '
    }

    return 'Search '
})

const tableSearchDescription = computed(() => {
    if (isAllDocumentsSection.value) {
        return 'Search the complete document registry across document number, type, offices, subject, dates, status, personnel, and remarks.'
    }

    if (isAddressedDocumentsSection.value) {
        return 'Search addressed documents that were received and already have a selected action.'
    }

    return 'Search checks all visible table columns, including document number, type, offices, subject, dates, status, personnel, and remarks.'
})

const availableYears = computed(() => {
    const yearList = (props.years || [])
        .map((year) => String(year))
        .filter(Boolean)

    const currentYear = String(new Date().getFullYear())

    if (!yearList.includes(currentYear)) {
        yearList.unshift(currentYear)
    }

    return [...new Set(yearList)]
})

const selectedYear = ref(String(
    currentParams.value.get('year') ||
    props.filters?.year ||
    availableYears.value[0] ||
    new Date().getFullYear()
))

const showYearFilter = computed(() => {
    return activeSection.value !== 'about'
})

const buildCurrentPayload = () => {
    const payload = {
        per_page: perPage.value,
    }

    if (selectedYear.value === 'all') {
        payload.year = 'all'
    } else if (selectedYear.value) {
        payload.year = selectedYear.value
    }

    if (activeSection.value !== 'documents') {
        payload.section = activeSection.value
    }

    if (activeFilter.value) {
        payload.filter = activeFilter.value
    }

    if (search.value) {
        payload.search = search.value
    }

    if (activeSection.value === 'received-docs') {
        if (receivedKeeper.value) {
            payload.keeper = receivedKeeper.value
        }

        if (receivedDocType.value) {
            payload.doc_type = receivedDocType.value
        }
    }

    if (activeSection.value === 'reports') {
        if (reportClassification.value) {
            payload.report_classification = reportClassification.value
        }

        if (reportMonth.value) {
            payload.report_month = reportMonth.value
        }
    }

    return payload
}

const applyYearFilter = () => {
    router.get('/dts', buildCurrentPayload(), {
        preserveScroll: true,
        replace: true,
    })
}

const receivedKeeper = ref(currentParams.value.get('keeper') || '')
const receivedDocType = ref(currentParams.value.get('doc_type') || '')
const reportClassification = ref(currentParams.value.get('report_classification') || '')
const reportMonth = ref(currentParams.value.get('report_month') || '')
const reportErrors = ref({})

const reportMonths = [
    { value: '', label: 'All Months' },
    { value: '1', label: 'January' },
    { value: '2', label: 'February' },
    { value: '3', label: 'March' },
    { value: '4', label: 'April' },
    { value: '5', label: 'May' },
    { value: '6', label: 'June' },
    { value: '7', label: 'July' },
    { value: '8', label: 'August' },
    { value: '9', label: 'September' },
    { value: '10', label: 'October' },
    { value: '11', label: 'November' },
    { value: '12', label: 'December' },
]

const reportMonthLabel = computed(() => {
    return reportMonths.find((month) => month.value === String(reportMonth.value))?.label || 'All Months'
})

const previewReport = () => {
    reportErrors.value = {}

    router.get('/dts', {
        section: 'reports',
        year: selectedYear.value === 'all' ? 'all' : (selectedYear.value || undefined),
        report_classification: reportClassification.value,
        report_month: reportMonth.value,
        per_page: perPage.value,
    }, {
        preserveScroll: true,
        replace: true,
    })
}

const resetReport = () => {
    reportClassification.value = ''
    reportMonth.value = ''
    reportErrors.value = {}

    router.get('/dts', {
        section: 'reports',
        year: selectedYear.value === 'all' ? 'all' : (selectedYear.value || undefined),
        per_page: perPage.value,
    }, {
        preserveScroll: true,
        replace: true,
    })
}

const pageTitle = computed(() => {
    if (activeSection.value === 'search') return 'Search'
    if (activeSection.value === 'reports') return 'Reports'
    if (activeSection.value === 'about') return 'About'
    if (activeSection.value === 'all-documents') return 'All Documents'
    if (activeSection.value === 'addressed-docs') return 'Addressed Documents'
    if (activeSection.value === 'incoming') return 'Incoming Documents'
    if (activeSection.value === 'outgoing') return 'Outgoing'
    if (activeSection.value === 'collaboration') return 'Incoming Documents'
    if (activeSection.value === 'received-docs') return 'Incoming Documents'
    if (activeSection.value === 'pending-docs') return 'Pending Documents'
    if (activeSection.value === 'pending-docs-07') return 'Pending Documents 07'
    if (activeSection.value === 'sent-docs') return 'Sent Documents'
    if (activeSection.value === 'pulled-out-docs') return 'Pulled Out Documents'

    if (activeFilter.value === 'for-receiving') return 'For Receiving'
    if (['collab-received', 'received'].includes(activeFilter.value)) return 'Received'
    if (activeFilter.value === 'for-action') return 'For Action'
    if (activeFilter.value === 'addressed') return 'Addressed'
    if (activeFilter.value === 'returned') return 'Returned'

    return 'Documents'
})

const isPendingDocs07 = computed(() => {
    return activeSection.value === 'pending-docs-07'
})

const incomingSections = ['incoming', 'received-docs', 'pending-docs', 'pending-docs-07', 'addressed-docs']
const outgoingSections = ['outgoing', 'sent-docs', 'pulled-out-docs']
const collaborationFilters = ['for-receiving', 'received', 'collab-received', 'for-action', 'addressed', 'returned']

const isIncomingGroup = computed(() => {
    return incomingSections.includes(activeSection.value)
})

const isOutgoingGroup = computed(() => {
    return outgoingSections.includes(activeSection.value)
})

const isCollaborationGroup = computed(() => {
    return activeSection.value === 'collaboration'
        || collaborationFilters.includes(activeFilter.value)
})

const showPageTabs = computed(() => {
    return isOutgoingGroup.value
})

const pageTabsTitle = computed(() => {
    if (isIncomingGroup.value) return 'Incoming Documents'
    if (isOutgoingGroup.value) return 'Outgoing Documents'
    if (isCollaborationGroup.value) return 'Collaboration'

    return ''
})

const pageTabsDescription = computed(() => {
    if (isIncomingGroup.value) return 'Choose what type of incoming documents you want to view.'
    if (isOutgoingGroup.value) return 'Choose what type of outgoing documents you want to view.'
    if (isCollaborationGroup.value) return 'Choose the incoming status you want to view.'

    return ''
})

const buildDtsUrl = (params = {}) => {
    const query = new URLSearchParams()

    if (selectedYear.value === 'all') {
        query.set('year', 'all')
    } else if (selectedYear.value) {
        query.set('year', selectedYear.value)
    }

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            query.set(key, value)
        }
    })

    const queryString = query.toString()

    return queryString ? `/dts?${queryString}` : '/dts'
}

const incomingTabs = computed(() => {
    return [
        {
            label: 'For Receiving',
            href: buildDtsUrl({ section: 'incoming', filter: 'for-receiving' }),
            active: activeFilter.value === 'for-receiving',
            count: props.stats.for_receiving ?? 0,
        },
        {
            label: 'Received',
            href: buildDtsUrl({ section: 'incoming', filter: 'received' }),
            active: ['received', 'collab-received'].includes(activeFilter.value),
            count: props.stats.received ?? 0,
        },
        {
            label: 'Addressed',
            href: buildDtsUrl({ section: 'addressed-docs' }),
            active: activeSection.value === 'addressed-docs' || activeFilter.value === 'addressed',
            count: addressedCount.value,
        },
        {
            label: 'For Action',
            href: buildDtsUrl({ section: 'incoming', filter: 'for-action' }),
            active: activeFilter.value === 'for-action',
            count: null,
        },
        {
            label: 'Returned',
            href: buildDtsUrl({ section: 'incoming', filter: 'returned' }),
            active: activeFilter.value === 'returned',
            count: props.stats.returned ?? 0,
        },
    ]
})

const outgoingTabs = computed(() => {
    return [
        {
            label: 'Sent Documents',
            href: buildDtsUrl({ section: 'sent-docs' }),
            active: activeSection.value === 'sent-docs',
            count: null,
        },
        {
            label: 'Pulled Out Documents',
            href: buildDtsUrl({ section: 'pulled-out-docs' }),
            active: activeSection.value === 'pulled-out-docs',
            count: null,
        },
    ]
})

const collaborationTabs = computed(() => {
    return [
        {
            label: 'For Receiving',
            href: buildDtsUrl({ section: 'incoming', filter: 'for-receiving' }),
            active: activeFilter.value === 'for-receiving',
            count: props.stats.for_receiving,
        },
        {
            label: 'Received',
            href: buildDtsUrl({ section: 'incoming', filter: 'received' }),
            active: ['received', 'collab-received'].includes(activeFilter.value),
            count: props.stats.received,
        },
        {
            label: 'Addressed',
            href: buildDtsUrl({ section: 'addressed-docs' }),
            active: activeSection.value === 'addressed-docs' || activeFilter.value === 'addressed',
            count: addressedCount.value,
        },
        {
            label: 'For Action',
            href: buildDtsUrl({ section: 'incoming', filter: 'for-action' }),
            active: activeFilter.value === 'for-action',
            count: null,
        },
        {
            label: 'Returned',
            href: buildDtsUrl({ section: 'incoming', filter: 'returned' }),
            active: activeFilter.value === 'returned',
            count: props.stats.returned,
        },
    ]
})

const pageTabs = computed(() => {
    if (isIncomingGroup.value || isCollaborationGroup.value) return incomingTabs.value
    if (isOutgoingGroup.value) return outgoingTabs.value

    return []
})


const pageTabCount = (tab) => {
    if (tab.count !== null && tab.count !== undefined) {
        return tab.count
    }

    return null
}

const isActivePageTab = (tab) => {
    return Boolean(tab.active)
}



const isGroupLandingPage = computed(() => {
    return activeSection.value === 'outgoing'
        || (
            activeSection.value === 'collaboration'
            && !activeFilter.value
        )
})

const rows = computed(() => {
    if (Array.isArray(props.documents)) {
        return props.documents
    }

    return props.documents?.data || []
})

const links = computed(() => {
    if (Array.isArray(props.documents)) {
        return []
    }

    return props.documents?.links || []
})

const paginationFrom = computed(() => {
    if (Array.isArray(props.documents)) {
        return rows.value.length ? 1 : 0
    }

    return props.documents?.from || 0
})

const paginationTo = computed(() => {
    if (Array.isArray(props.documents)) {
        return rows.value.length
    }

    return props.documents?.to || 0
})

const paginationTotal = computed(() => {
    if (Array.isArray(props.documents)) {
        return rows.value.length
    }

    return props.documents?.total || 0
})

const currentPage = computed(() => {
    if (Array.isArray(props.documents)) {
        return 1
    }

    return props.documents?.current_page || 1
})

const lastPage = computed(() => {
    if (Array.isArray(props.documents)) {
        return 1
    }

    return props.documents?.last_page || 1
})

const openAddDocumentModal = () => {
    if (!canManageDts.value) return

    showAddDocumentModal.value = true
}

const closeAddDocumentModal = () => {
    showAddDocumentModal.value = false
}

const applyFilters = () => {
    router.get('/dts', buildCurrentPayload(), {
        preserveScroll: true,
        replace: true,
    })
}

const runSearch = () => {
    applyFilters()
}

watch(search, () => {
    if (skipNextSearchWatch) {
        skipNextSearchWatch = false
        clearTimeout(searchTimer)
        return
    }

    clearTimeout(searchTimer)

    searchTimer = setTimeout(() => {
        applyFilters()
    }, 500)
})

onBeforeUnmount(() => {
    clearTimeout(searchTimer)
})

const resetSearch = () => {
    skipNextSearchWatch = true
    search.value = ''
    perPage.value = 10

    const payload = {
        per_page: perPage.value,
    }

    if (selectedYear.value) {
        payload.year = selectedYear.value
    }

    if (activeSection.value !== 'documents') {
        payload.section = activeSection.value
    }

    if (activeFilter.value) {
        payload.filter = activeFilter.value
    }

    router.get('/dts', payload, {
        preserveScroll: true,
        replace: true,
    })
}

const applyReceivedFilters = () => {
    const payload = {
        section: 'received-docs',
        year: selectedYear.value === 'all' ? 'all' : (selectedYear.value || undefined),
        keeper: receivedKeeper.value,
        doc_type: receivedDocType.value,
        per_page: perPage.value,
    }

    if (search.value) {
        payload.search = search.value
    }

    router.get('/dts', payload, {
        preserveScroll: true,
        replace: true,
    })
}

const resetReceivedFilters = () => {
    skipNextSearchWatch = true
    search.value = ''
    receivedKeeper.value = ''
    receivedDocType.value = ''

    router.get('/dts', {
        section: 'received-docs',
        year: selectedYear.value === 'all' ? 'all' : (selectedYear.value || undefined),
        per_page: perPage.value,
    }, {
        preserveScroll: true,
        replace: true,
    })
}


const receiveTransferredDocument = (doc) => {
    if (!canReceiveDts.value || !doc?.IDdoc) return

    router.post(`/dts/${doc.IDdoc}/receive`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({
                preserveScroll: true,
            })
        },
    })
}

const receivePendingDocument = (doc) => {
    if (!canManageDts.value) return
    if (!doc?.IDdoc) return

    selectedPendingDocument.value = doc
    pendingActionType.value = 'receive'
    showPendingActionModal.value = true
}

const pulloutPendingDocument = (doc) => {
    if (!canManageDts.value) return
    if (!doc?.IDdoc) return

    selectedPendingDocument.value = doc
    pendingActionType.value = 'pullout'
    showPendingActionModal.value = true
}

const closePendingActionModal = () => {
    if (pendingActionProcessing.value) return

    showPendingActionModal.value = false
    selectedPendingDocument.value = null
    pendingActionType.value = ''
}

const confirmPendingAction = () => {
    if (!canManageDts.value) return
    if (!selectedPendingDocument.value?.IDdoc || !pendingActionType.value) return

    pendingActionProcessing.value = true

    const documentId = selectedPendingDocument.value.IDdoc
    const endpoint = pendingActionType.value === 'receive'
        ? `/dts/${documentId}/receive`
        : `/dts/${documentId}/pullout`

    router.post(endpoint, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showPendingActionModal.value = false
            selectedPendingDocument.value = null
            pendingActionType.value = ''

            router.reload({
                preserveScroll: true,
            })
        },
        onFinish: () => {
            pendingActionProcessing.value = false
        },
    })
}

const goToPage = (url) => {
    if (!url) return

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    })
}



const documentStatusLabel = (doc) => {
    return doc.workflow_status
        || doc.status_label
        || doc.status
        || '-'
}

const selectedActionLabel = (doc) => {
    return doc.selected_action
        || doc.action_label
        || doc.action_name
        || doc.selected_action_name
        || 'No selected action'
}

const selectedActionClass = (doc) => {
    return selectedActionLabel(doc) === 'No selected action'
        ? 'border-slate-300 bg-slate-100 text-slate-700'
        : 'border-cyan-300 bg-cyan-100 text-cyan-800'
}

const documentStatusClass = (doc) => {
    const status = String(documentStatusLabel(doc)).toLowerCase()

    if (status === 'received' || status.includes('received')) {
        return 'border-green-300 bg-green-100 text-green-800'
    }

    if (status === 'for receiving' || status.includes('for receiving')) {
        return 'border-blue-300 bg-blue-100 text-blue-800'
    }

    if (status.includes('pending 07')) {
        return 'border-orange-300 bg-orange-100 text-orange-800'
    }

    if (status.includes('pending')) {
        return 'border-yellow-300 bg-yellow-100 text-yellow-900'
    }

    if (status.includes('return')) {
        return 'border-red-300 bg-red-100 text-red-800'
    }

    if (status.includes('pulled')) {
        return 'border-slate-300 bg-slate-100 text-slate-800'
    }

    return 'border-slate-300 bg-slate-100 text-slate-700'
}

const canShowReceiveButton = (doc) => {
    return canReceiveDts.value && documentStatusLabel(doc) === 'For Receiving'
}


const formatClassification = (value) => {
    const classification = String(value || '').toLowerCase()

    if (classification === 'false' || classification === 'incoming') {
        return 'Incoming'
    }

    if (classification === 'true' || classification === 'outgoing') {
        return 'Outgoing'
    }

    return '-'
}

const classificationBadgeClass = (value) => {
    const label = formatClassification(value)

    if (label === 'Incoming') {
        return 'border border-green-300 bg-green-100 text-green-800'
    }

    if (label === 'Outgoing') {
        return 'border border-blue-300 bg-blue-100 text-blue-800'
    }

    return 'border border-slate-300 bg-slate-100 text-slate-800'
}


const formatDtsId = (value) => {
    if (value === null || value === undefined || String(value).trim() === '') {
        return 'DTS - #'
    }

    const rawValue = String(value).trim()
    const cleanValue = rawValue
        .replace(/^DTS\s*-\s*#?/i, '')
        .replace(/^#/, '')
        .trim()

    return `DTS - #${cleanValue || rawValue}`
}

const formatDtsDocumentNo = (doc) => {
    return formatDtsId(doc?.document_no || doc?.tracking_no || doc?.IDdoc || doc?.id)
}

const printReport = () => {
    window.setTimeout(() => {
        window.print()
    }, 100)
}

const formatDateTime = (value, emptyText = '-') => {
    if (!value) {
        return emptyText
    }

    const normalizedValue = String(value).replace(' ', 'T')
    const date = new Date(normalizedValue)

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

const formatDateForInput = (value) => {
    if (!value) return ''

    const normalizedValue = String(value).replace(' ', 'T')
    const date = new Date(normalizedValue)

    if (Number.isNaN(date.getTime())) {
        return String(value).slice(0, 16).replace(' ', 'T')
    }

    const pad = (number) => String(number).padStart(2, '0')

    return [
        date.getFullYear(),
        pad(date.getMonth() + 1),
        pad(date.getDate()),
    ].join('-') + `T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const openEditEntryDateModal = (document) => {
    if (!canManageDts.value) return

    selectedDocument.value = document
    entryDateForm.clearErrors()
    entryDateForm.entrydate = formatDateForInput(document.entrydate)
    showEditEntryDateModal.value = true
}

const closeEditEntryDateModal = () => {
    showEditEntryDateModal.value = false
    selectedDocument.value = null
    entryDateForm.reset()
    entryDateForm.clearErrors()
}

const submitEntryDateUpdate = () => {
    if (!canManageDts.value) return
    if (!selectedDocument.value?.IDdoc) return

    entryDateForm.patch(`/dts/${selectedDocument.value.IDdoc}/entry-date`, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditEntryDateModal()
        },
    })
}
</script>

<template>
    <Head title="Document Tracking System" />

    <DTSLayout
        :stats="props.stats"
        :notification-count="transferNotificationCount"
        @open-add-document="openAddDocumentModal"
        @open-notifications="openNotificationsFromBell"
    >
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-screen-2xl px-6 py-5 lg:px-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                            {{ pageTitle }}
                        </h1>

                       
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <div
                            v-if="showYearFilter"
                            class="flex w-full items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 sm:w-auto"
                        >
                            <label class="shrink-0 text-sm font-bold text-blue-800">
                                Year:
                            </label>

                            <div class="min-w-[11rem] flex-1 sm:flex-none">
                                <select
                                    v-model="selectedYear"
                                    class="h-11 w-full rounded-xl border border-blue-300 bg-white px-4 py-2.5 text-sm font-bold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    @change="applyYearFilter"
                                >
                                    <option value="all">
                                        All Years
                                    </option>

                                    <option
                                        v-for="year in availableYears"
                                        :key="year"
                                        :value="year"
                                    >
                                        {{ year }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <button
                            v-if="activeSection === 'documents' && canManageDts"
                            type="button"
                            class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                            @click="openAddDocumentModal"
                        >
                            + New Document
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-screen-2xl px-6 py-8 lg:px-8">
            <!-- Flash Messages -->
            <div
                v-if="flashSuccess || flashError || firstErrorMessage"
                class="mb-6 space-y-3"
            >
                <div
                    v-if="flashSuccess"
                    class="rounded-2xl border border-green-300 bg-green-50 px-5 py-4 text-sm font-bold text-green-800"
                >
                    {{ flashSuccess }}
                </div>

                <div
                    v-if="flashError"
                    class="rounded-2xl border border-red-300 bg-red-50 px-5 py-4 text-sm font-bold text-red-800"
                >
                    {{ flashError }}
                </div>

                <div
                    v-if="firstErrorMessage"
                    class="rounded-2xl border border-red-300 bg-red-50 px-5 py-4 text-sm font-bold text-red-800"
                >
                    {{ firstErrorMessage }}
                </div>
            </div>

            <!-- Document Notification Popup -->
            <div
                v-if="showTransferNotificationModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8"
            >
                <div class="max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                    <div class="border-b border-blue-100 bg-blue-600 px-6 py-5 text-white">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                                    Document Notification
                                </p>

                                <h2 class="mt-2 text-2xl font-black">
                                    Document Notifications
                                </h2>

                                <p class="mt-1 text-sm font-semibold text-blue-100">
                                    These are documents waiting for receive action and documents you added that were already received.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="rounded-xl bg-white/15 px-4 py-2 text-sm font-black text-white hover:bg-white/25"
                                @click="closeTransferNotificationModal"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                    <div class="p-6">
                        <template v-if="hasTransferNotifications">
                        <div
                            v-if="overdueTransferNotifications.length"
                            class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-bold text-red-700"
                        >
                            Alert: {{ overdueTransferNotifications.length }} document(s) are already beyond 7 days without receive action.
                        </div>

                        <div class="max-h-[55vh] space-y-3 overflow-y-auto pr-1">
                            <div
                                v-for="doc in transferNotifications"
                                :key="`document-notification-${doc.notification_type}-${doc.IDdoc}`"
                                class="rounded-2xl border p-4"
                                :class="doc.is_overdue
                                    ? 'border-red-200 bg-red-50'
                                    : 'border-blue-100 bg-blue-50'"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-blue-700">
                                                {{ formatDtsDocumentNo(doc) }}
                                            </span>

                                            <span
                                                v-if="doc.notification_type === 'received_by_addressee'"
                                                class="rounded-full bg-green-600 px-3 py-1 text-xs font-black text-white"
                                            >
                                                Received
                                            </span>

                                            <span
                                                v-else-if="doc.is_overdue"
                                                class="rounded-full bg-red-600 px-3 py-1 text-xs font-black text-white"
                                            >
                                                Overdue
                                            </span>

                                            <span
                                                v-else
                                                class="rounded-full bg-blue-600 px-3 py-1 text-xs font-black text-white"
                                            >
                                                For Receiving
                                            </span>
                                        </div>

                                        <p class="mt-3 break-words text-base font-black text-slate-900">
                                            {{ doc.subject || 'No subject' }}
                                        </p>

                                        <div class="mt-3 grid grid-cols-1 gap-2 text-sm font-semibold text-slate-700 md:grid-cols-2">
                                            <p>
                                                <span class="font-black text-slate-900">
                                                    {{ doc.notification_type === 'received_by_addressee' ? 'Received:' : 'Transferred:' }}
                                                </span>
                                                {{ formatDateTime(doc.notification_type === 'received_by_addressee' ? doc.received_date : doc.transfer_date) }}
                                            </p>

                                            <p v-if="doc.notification_type === 'received_by_addressee'">
                                                <span class="font-black text-slate-900">Received By:</span>
                                                {{ doc.received_by || '-' }}
                                            </p>

                                            <p v-else>
                                                <span class="font-black text-slate-900">Receive Due:</span>
                                                {{ formatDateTime(doc.due_date) }}
                                            </p>

                                            <p>
                                                <span class="font-black text-slate-900">From:</span>
                                                {{ doc.from_office || '-' }}
                                            </p>

                                            <p>
                                                <span class="font-black text-slate-900">
                                                    {{ doc.notification_type === 'received_by_addressee' ? 'Received Office:' : 'To:' }}
                                                </span>
                                                {{ doc.transferred_to || doc.received_office || '-' }}
                                            </p>
                                        </div>

                                        <p
                                            v-if="doc.notification_type !== 'received_by_addressee' && doc.is_overdue"
                                            class="mt-3 rounded-xl bg-white px-4 py-3 text-sm font-black text-red-700"
                                        >
                                            No receive action after 7 days. Please tag this document as received.
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-col gap-2">
                                        <button
                                            v-if="doc.notification_type !== 'received_by_addressee'"
                                            type="button"
                                            class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-black text-white hover:bg-blue-700"
                                            @click="receiveTransferredDocument(doc)"
                                        >
                                            Receive Document
                                        </button>

                                        <Link
                                            :href="`/dts/${doc.IDdoc}`"
                                            class="rounded-xl border border-blue-300 bg-white px-5 py-2.5 text-center text-sm font-black text-blue-700 hover:bg-blue-50"
                                            @click="markNotificationSeen(doc)"
                                        >
                                            View Details
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        </template>

                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center"
                        >
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-2xl shadow-sm">
                                🔔
                            </div>

                            <h3 class="mt-4 text-xl font-black text-slate-900">
                                No notifications
                            </h3>

                            <p class="mt-2 text-sm font-semibold text-slate-600">
                                You have no document notifications at the moment.
                            </p>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-black text-slate-700 hover:bg-slate-50"
                                @click="closeTransferNotificationModal"
                            >
                                {{ hasTransferNotifications ? 'Review Later' : 'Close' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PAGE TAB BUTTONS -->
            <div
                v-if="showPageTabs"
                class="mb-6 rounded-3xl border border-slate-200 bg-white p-3 shadow-sm"
            >
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-for="tab in pageTabs"
                        :key="tab.label"
                        :href="tab.href"
                        class="inline-flex min-h-[48px] items-center gap-3 rounded-2xl border px-5 py-3 text-sm font-bold transition-all"
                        :class="tab.active
                            ? 'border-blue-600 bg-blue-600 text-white shadow-md shadow-blue-100'
                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700'"
                    >

                        <span class="whitespace-nowrap">
                            {{ tab.label }}
                        </span>

                        <span
                            v-if="pageTabCount(tab) !== null"
                            class="rounded-full px-2 py-0.5 text-xs font-black"
                            :class="isActivePageTab(tab)
                                ? 'bg-white/20 text-white'
                                : 'bg-blue-100 text-blue-700'"
                        >
                            {{ pageTabCount(tab) }}
                        </span>


                    </Link>
                </div>
            </div>

            <!-- GROUP LANDING CONTENT -->
            <div
                v-if="isGroupLandingPage"
                class="rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm"
            >
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-2xl">
                    📄
                </div>

                <p class="mt-4 text-sm font-bold text-slate-700">
                    Select a tab above to view the records.
                </p>
            </div>

                       <!-- ABOUT CONTENT -->
            <div
                v-else-if="activeSection === 'about'"
                class="space-y-6"
            >
                <!-- Hero -->
                <section class="overflow-hidden rounded-3xl border border-blue-100 bg-white shadow-sm">
                    <div class="grid grid-cols-1 lg:grid-cols-[1.15fr_0.85fr]">
                        <div class="p-8 lg:p-10">
                            <div class="inline-flex items-center gap-2 rounded-full bg-blue-50 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-blue-700">
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                About the System
                            </div>

                            <h2 class="mt-6 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                                 Document Tracking System
                            </h2>

                            <p class="mt-4 max-w-4xl text-base font-semibold leading-8 text-slate-600">
                                 DTS is a web-based document tracking platform designed to help offices
                                encode, receive, route, return, monitor, and manage official documents in one
                                organized workspace. It improves visibility of document movement and helps users
                                quickly identify pending actions, assigned personnel, and document history.
                            </p>

                            <div class="mt-7 flex flex-wrap gap-3">
                                <span class="rounded-full bg-blue-600 px-5 py-2 text-sm font-black text-white">
                                    Document Monitoring
                                </span>

                                <span class="rounded-full bg-emerald-50 px-5 py-2 text-sm font-black text-emerald-700 ring-1 ring-emerald-100">
                                    Routing & Receiving
                                </span>

                                <span class="rounded-full bg-purple-50 px-5 py-2 text-sm font-black text-purple-700 ring-1 ring-purple-100">
                                    Action History
                                </span>
                            </div>
                        </div>

                        <div class="bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-600 p-8 text-white lg:p-10">
                            <p class="text-sm font-black uppercase tracking-[0.22em] text-blue-100">
                                System Purpose
                            </p>

                            <div class="mt-6 space-y-4">
                                <div class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15">
                                    <p class="text-2xl font-black">
                                        Faster Tracking
                                    </p>

                                    <p class="mt-2 text-sm font-semibold leading-6 text-blue-100">
                                        Quickly locate documents and see their current movement status.
                                    </p>
                                </div>

                                <div class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15">
                                    <p class="text-2xl font-black">
                                        Clear Accountability
                                    </p>

                                    <p class="mt-2 text-sm font-semibold leading-6 text-blue-100">
                                        Know who received, returned, transferred, or still needs to act on a document.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Feature Cards -->
                <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-3xl border border-blue-100 bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-2xl">
                            📄
                        </div>

                        <h3 class="mt-5 text-lg font-black text-slate-900">
                            Document Encoding
                        </h3>

                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-500">
                            Add document details, classification, document type, concerned staff, and attachments.
                        </p>
                    </div>

                    <div class="rounded-3xl border border-emerald-100 bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-2xl">
                            ✅
                        </div>

                        <h3 class="mt-5 text-lg font-black text-slate-900">
                            Receiving
                        </h3>

                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-500">
                            Mark documents as received and confirm that the assigned office or personnel has taken action.
                        </p>
                    </div>

                    <div class="rounded-3xl border border-purple-100 bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-50 text-2xl">
                            🔁
                        </div>

                        <h3 class="mt-5 text-lg font-black text-slate-900">
                            Transfer & Return
                        </h3>

                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-500">
                            Forward documents to the proper personnel or return them with remarks when needed.
                        </p>
                    </div>

                    <div class="rounded-3xl border border-amber-100 bg-white p-6 shadow-sm">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-2xl">
                            📊
                        </div>

                        <h3 class="mt-5 text-lg font-black text-slate-900">
                            Monitoring Reports
                        </h3>

                        <p class="mt-2 text-sm font-semibold leading-6 text-slate-500">
                            View transaction summaries, pending actions, and monitoring dashboard reports.
                        </p>
                    </div>
                </section>

                <!-- Workflow -->
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-black uppercase tracking-[0.22em] text-blue-600">
                                Document Flow
                            </p>

                            <h3 class="mt-2 text-2xl font-black text-slate-950">
                                How documents move in DTS
                            </h3>
                        </div>

                        <p class="max-w-2xl text-sm font-semibold leading-6 text-slate-500">
                            The system keeps every step visible so users can check where a document is, who should act on it,
                            and what actions were already performed.
                        </p>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-5">
                        <div class="rounded-2xl bg-slate-50 p-5 text-center ring-1 ring-slate-100">
                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-sm font-black text-white">
                                1
                            </div>
                            <p class="mt-3 text-sm font-black text-slate-900">Encode</p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-5 text-center ring-1 ring-slate-100">
                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-sm font-black text-white">
                                2
                            </div>
                            <p class="mt-3 text-sm font-black text-slate-900">Assign</p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-5 text-center ring-1 ring-slate-100">
                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-sm font-black text-white">
                                3
                            </div>
                            <p class="mt-3 text-sm font-black text-slate-900">Receive</p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-5 text-center ring-1 ring-slate-100">
                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-sm font-black text-white">
                                4
                            </div>
                            <p class="mt-3 text-sm font-black text-slate-900">Transfer / Return</p>
                        </div>

                        <div class="rounded-2xl bg-slate-50 p-5 text-center ring-1 ring-slate-100">
                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 text-sm font-black text-white">
                                5
                            </div>
                            <p class="mt-3 text-sm font-black text-slate-900">Monitor</p>
                        </div>
                    </div>
                </section>

                <!-- Footer Info -->
                <section class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div class="rounded-3xl border border-blue-100 bg-blue-50 p-6">
                        <h3 class="text-lg font-black text-blue-900">
                            Who can use the system?
                        </h3>

                        <p class="mt-3 text-sm font-semibold leading-7 text-blue-800">
                            DTS is intended for authorized users who encode, route, receive, return, monitor,
                            and manage office documents. Access is role-based so each user only sees the modules
                            and actions allowed for their role.
                        </p>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-black text-slate-900">
                            Need assistance?
                        </h3>

                        <p class="mt-3 text-sm font-semibold leading-7 text-slate-500">
                            For account access, incorrect document routing, missing notifications, or report concerns,
                            contact the system administrator or the assigned DTS monitoring staff.
                        </p>
                    </div>
                </section>
            </div>


            <!-- REPORTS CONTENT -->
            <div
                v-else-if="activeSection === 'reports'"
                class="space-y-6"
            >
                <div class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm no-print">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl border border-blue-200 bg-blue-50 p-3 shadow-sm">
                                <img
                                    src="/images/dost-logo.png"
                                    alt="DOST Logo"
                                    class="h-full w-full object-contain"
                                />
                            </div>

                            <div>
                                <p class="text-sm font-bold uppercase tracking-wide text-blue-700">
                                    Reports Menu
                                </p>

                                <h2 class="mt-2 text-3xl font-bold text-slate-900">
                                    DTS Monthly Reports
                                </h2>

                                <p class="mt-2 text-sm font-semibold text-slate-500">
                                    Select classification and month. Results update automatically.
                                </p>
                            </div>
                        </div>

                        
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 no-print">
                    <section class="rounded-2xl border border-blue-200 bg-white shadow-sm">
                        <div class="border-b border-blue-100 px-6 py-5">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-xl font-bold text-black">
                                        Reports: By Classification and Month
                                    </h3>

                                    <p class="mt-1 text-sm font-medium text-black">
                                        Choose Incoming or Outgoing, then select the month to display only matching records.
                                    </p>
                                </div>

                                <span class="inline-flex w-fit rounded-full bg-blue-700 px-4 py-1.5 text-xs font-bold text-white">
                                    Auto Filter
                                </span>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-bold text-black">
                                        Classification
                                    </label>

                                    <select
                                        v-model="reportClassification"
                                        class="w-full rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        @change="previewReport"
                                    >
                                        <option value="">
                                            All Classifications
                                        </option>

                                        <option value="False">
                                            Incoming
                                        </option>

                                        <option value="True">
                                            Outgoing
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-bold text-black">
                                        Month
                                    </label>

                                    <select
                                        v-model="reportMonth"
                                        class="w-full rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        @change="previewReport"
                                    >
                                        <option
                                            v-for="month in reportMonths"
                                            :key="month.value || 'all-months'"
                                            :value="month.value"
                                        >
                                            {{ month.label }}
                                        </option>
                                    </select>
                                </div>

                                <div
                                    v-if="reportErrors.general"
                                    class="rounded-xl border border-red-300 bg-red-50 px-5 py-4 text-sm font-bold text-red-800 lg:col-span-2"
                                >
                                    {{ reportErrors.general }}
                                </div>

                                <div class="flex flex-col justify-end gap-3 border-t border-blue-100 pt-5 sm:flex-row lg:col-span-2">
                                    <button
                                        type="button"
                                        class="rounded-xl bg-green-600 px-7 py-3 text-sm font-bold text-white shadow-sm hover:bg-green-700"
                                        @click="printReport"
                                    >
                                        Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="report-print-area rounded-2xl border border-blue-200 bg-white shadow-sm">
                    <div class="border-b border-blue-100 bg-blue-600 px-6 py-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white p-2 shadow-sm">
                                    <img
                                        src="/images/dost-logo.png"
                                        alt="DOST Logo"
                                        class="h-full w-full object-contain"
                                    />
                                </div>

                                <div>
                                    <h3 class="text-xl font-bold text-white">
                                        Report Preview
                                    </h3>

                                    <p class="mt-1 text-sm font-medium text-white">
                                        Document Tracking System
                                    </p>

                                    <p class="mt-1 text-xs font-semibold text-blue-100">
                                        Showing {{ paginationFrom }} to {{ paginationTo }} of {{ paginationTotal }} entries
                                    </p>
                                </div>
                            </div>

                            <div class="text-left text-xs font-semibold text-white md:text-right">
                                <p>
                                    Classification:
                                    <span class="font-bold">
                                        {{ reportClassification ? formatClassification(reportClassification) : 'All' }}
                                    </span>
                                </p>

                                <p class="mt-1">
                                    Month:
                                    <span class="font-bold">
                                        {{ reportMonthLabel }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="screen-report-table w-full min-w-[1200px] table-fixed border-collapse text-center text-sm">
                            <thead>
                                <tr class="bg-blue-50 text-black">
                                    <th class="w-[9%] border border-black px-4 py-4 font-bold">
                                        DOC ID
                                    </th>

                                    <th class="w-[11%] border border-black px-4 py-4 font-bold">
                                        Classification
                                    </th>

                                    <th class="w-[10%] border border-black px-4 py-4 font-bold">
                                        Type
                                    </th>

                                    <th class="w-[15%] border border-black px-4 py-4 font-bold">
                                        Entry Date
                                    </th>

                                    <th class="w-[17%] border border-black px-4 py-4 font-bold">
                                        From
                                    </th>

                                    <th class="w-[17%] border border-black px-4 py-4 font-bold">
                                        To
                                    </th>

                                    <th class="w-[21%] border border-black px-4 py-4 font-bold">
                                        Subject
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(doc, index) in rows"
                                    :key="doc.IDdoc"
                                    :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-100'"
                                >
                                    <td class="border border-black px-4 py-4 font-bold text-blue-700">
                                        {{ formatDtsDocumentNo(doc) }}
                                    </td>

                                    <td class="border border-black px-4 py-4">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-bold"
                                            :class="classificationBadgeClass(doc.classification)"
                                        >
                                            {{ formatClassification(doc.classification) }}
                                        </span>
                                    </td>

                                    <td class="border border-black px-4 py-4 font-bold text-black">
                                        {{ doc.code || doc.abbreviation || doc.document_code || doc.doctype || '-' }}
                                    </td>

                                    <td class="border border-black px-4 py-4 font-bold text-black">
                                        {{ formatDateTime(doc.entrydate) }}
                                    </td>

                                    <td class="border border-black px-4 py-4 font-semibold text-black">
                                        {{ doc.from_office || '-' }}
                                    </td>

                                    <td class="border border-black px-4 py-4 font-semibold text-black">
                                        {{ doc.for_office || doc.current_office || '-' }}
                                    </td>

                                    <td class="border border-black px-4 py-4 font-bold text-black">
                                        {{ doc.subject || '-' }}
                                    </td>
                                </tr>

                                <tr v-if="rows.length === 0">
                                    <td colspan="7" class="border border-black px-7 py-14 text-center">
                                        <div class="text-lg font-bold text-black">
                                            No report records found
                                        </div>

                                        <p class="mt-2 text-sm font-medium text-black">
                                            Try another classification or month.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Print-only table: requested print columns only -->
                        <table class="print-report-table w-full table-fixed border-collapse text-center text-xs">
                            <thead>
                                <tr class="bg-blue-50 text-black">
                                    <th class="w-[10%] border border-black px-3 py-3 font-bold">
                                        DTS #
                                    </th>

                                    <th class="w-[14%] border border-black px-3 py-3 font-bold">
                                        Date
                                    </th>

                                    <th class="w-[18%] border border-black px-3 py-3 font-bold">
                                        To
                                    </th>

                                    <th class="w-[18%] border border-black px-3 py-3 font-bold">
                                        From
                                    </th>

                                    <th class="w-[25%] border border-black px-3 py-3 font-bold">
                                        Subject
                                    </th>

                                    <th class="w-[15%] border border-black px-3 py-3 font-bold">
                                        Remarks
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(doc, index) in rows"
                                    :key="`print-${doc.IDdoc}`"
                                    :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-100'"
                                >
                                    <td class="border border-black px-3 py-3 font-bold text-black">
                                        {{ formatDtsDocumentNo(doc) }}
                                    </td>

                                    <td class="border border-black px-3 py-3 font-semibold text-black">
                                        {{ formatDateTime(doc.entrydate) }}
                                    </td>

                                    <td class="border border-black px-3 py-3 font-semibold text-black">
                                        {{ doc.for_office || doc.current_office || '-' }}
                                    </td>

                                    <td class="border border-black px-3 py-3 font-semibold text-black">
                                        {{ doc.from_office || '-' }}
                                    </td>

                                    <td class="border border-black px-3 py-3 font-bold text-black">
                                        {{ doc.subject || '-' }}
                                    </td>

                                    <td class="border border-black px-3 py-3 font-semibold text-black">
                                        {{ doc.remarks || doc.distribution_remarks || '-' }}
                                    </td>
                                </tr>

                                <tr v-if="rows.length === 0">
                                    <td colspan="6" class="border border-black px-7 py-14 text-center">
                                        <div class="text-lg font-bold text-black">
                                            No report records found
                                        </div>

                                        <p class="mt-2 text-sm font-medium text-black">
                                            Try another classification or month.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- RECEIVED DOCS CONTENT -->
            <div
                v-else-if="activeSection === 'received-docs'"
                class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm"
            >
                <div class="mb-5">
                    <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                        Incoming Documents
                    </h2>

                    <p class="mt-2 text-sm font-medium text-black">
                        View and filter incoming documents received by keeper and document type.
                    </p>
                </div>

                <div class="mb-8 rounded-xl border border-blue-200 bg-blue-50 p-5">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                        <div class="lg:col-span-4">
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                :placeholder="tableSearchPlaceholder"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                @keyup.enter="applyReceivedFilters"
                            />
                        </div>

                        <div class="lg:col-span-3">
                            <label class="mb-2 block text-sm font-bold text-black">
                                By Keeper:
                            </label>

                            <select
                                v-model="receivedKeeper"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            >
                                <option value="">
                                    All
                                </option>

                                <option
                                    v-for="staff in props.staffConcerns"
                                    :key="staff.ID || staff.id"
                                    :value="staff.ID || staff.id"
                                >
                                    {{ staff.name || staff.personnel_name || '-' }}
                                </option>
                            </select>
                        </div>

                        <div class="lg:col-span-3">
                            <label class="mb-2 block text-sm font-bold text-black">
                                By Type:
                            </label>

                            <select
                                v-model="receivedDocType"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                @change="applyReceivedFilters"
                            >
                                <option value="">
                                    All
                                </option>

                                <option
                                    v-for="type in props.docTypes"
                                    :key="type.ID || type.id"
                                    :value="type.ID || type.id"
                                >
                                    {{ type.description || type.name || '-' }}
                                </option>
                            </select>
                        </div>

                        <div class="flex gap-2 lg:col-span-2">
                            <button
                                type="button"
                                class="w-full rounded-lg border border-blue-400 bg-white px-4 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                                @click="applyReceivedFilters"
                            >
                                Search
                            </button>

                            <button
                                type="button"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                                @click="resetReceivedFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-blue-300">
                    <table class="w-full min-w-[1100px] table-fixed border-collapse text-center text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                    DTS<br>#
                                </th>

                                <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                    Type
                                </th>

                                <th class="w-[18%] border border-black px-4 py-4 text-center font-bold">
                                    From
                                </th>

                                <th class="w-[35%] border border-black px-4 py-4 text-center font-bold">
                                    Subject
                                </th>

                                <th class="w-[18%] border border-black px-4 py-4 text-center font-bold">
                                    Date Received
                                </th>

                                <th class="w-[9%] border border-black px-4 py-4 text-center font-bold">
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(doc, index) in rows"
                                :key="doc.IDdoc"
                                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-100'"
                            >
                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="font-bold text-blue-700 hover:underline"
                                    >
                                        {{ formatDtsDocumentNo(doc) }}
                                    </Link>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ doc.code || doc.abbreviation || doc.document_code || doc.doctype || doc.type || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold italic leading-6 text-black">
                                        {{ doc.from_office || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                        {{ doc.subject || 'No subject' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ formatDateTime(doc.date_received || doc.received_date || doc.confirmdate || doc.entrydate) }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="inline-flex rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                                <td colspan="6" class="border border-black px-7 py-14 text-center">
                                    <div class="text-lg font-bold text-black">
                                        No incoming documents found
                                    </div>

                                    <p class="mt-2 text-sm font-medium text-black">
                                        Try another keeper, document type, or reset the filters.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="links.length > 3"
                    class="mt-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="link in links"
                            :key="`${link.label}-${link.url}`"
                            type="button"
                            :disabled="!link.url"
                            class="rounded-lg border px-3 py-2 text-sm font-bold"
                            :class="[
                                link.active
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-blue-300 bg-white text-blue-700 hover:bg-blue-50',
                                !link.url ? 'cursor-not-allowed opacity-50' : ''
                            ]"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        ></button>
                    </div>
                </div>
            </div>

          <!-- PENDING DOCS / PENDING DOCS 07 CONTENT -->
                <div
                v-else-if="activeSection === 'pending-docs' || activeSection === 'pending-docs-07'"
                class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm"
            >
                <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                            {{ isPendingDocs07 ? 'Pending Documents 07' : 'Pending Documents' }}
                        </h2>

                        <p class="mt-2 text-sm font-medium text-black">
                            {{ isPendingDocs07
                                ? (canManageDts ? 'List of pending 07 documents with pullout and receive actions.' : 'List of pending 07 documents for viewing only.')
                                : (canManageDts ? 'List of documents pending for receiving.' : 'List of pending documents for viewing only.')
                            }}
                        </p>
                    </div>

                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>
                </div>

                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                :placeholder="tableSearchPlaceholder"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                @keyup.enter="runSearch"
                            />
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-400 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="runSearch"
                        >
                            Search
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-300 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="resetSearch"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-black">
                    <table class="w-full min-w-[1100px] table-fixed border-collapse text-center text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="w-[9%] border border-black px-4 py-4 text-center font-bold">
                                    DTS<br>#
                                </th>

                                <th class="w-[11%] border border-black px-4 py-4 text-center font-bold">
                                    Type
                                </th>

                                <th class="w-[22%] border border-black px-4 py-4 text-center font-bold">
                                    To
                                </th>

                                <th class="w-[34%] border border-black px-4 py-4 text-center font-bold">
                                    Subject
                                </th>

                                <th class="w-[14%] border border-black px-4 py-4 text-center font-bold">
                                    Date Sent
                                </th>

                                <th
                                    v-if="canManageDts"
                                    class="w-[10%] border border-black px-4 py-4 text-center font-bold"
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>

            <tbody>
                    <tr
                        v-for="(doc, index) in rows"
                        :key="doc.IDdoc"
                        :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-100'"
                    >
                        <td class="border border-black px-4 py-4 align-middle text-center">
                            <Link
                                :href="`/dts/${doc.IDdoc}`"
                                class="font-bold text-blue-700 hover:underline"
                            >
                                {{ formatDtsDocumentNo(doc) }}
                            </Link>
                        </td>

                        <td class="border border-black px-4 py-4 align-middle text-center">
                            <p class="font-bold text-black">
                                {{ doc.code || doc.abbreviation || doc.document_code || doc.doctype || doc.type || '-' }}
                            </p>
                        </td>

                        <td class="border border-black px-4 py-4 align-middle text-center">
                            <p class="whitespace-pre-line break-words font-semibold italic leading-6 text-black">
                                {{ doc.for_office || doc.current_office || '-' }}
                            </p>
                        </td>

                        <td class="border border-black px-4 py-4 align-middle text-center">
                            <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                {{ doc.subject || 'No subject' }}
                            </p>
                        </td>

                        <td class="border border-black px-4 py-4 align-middle text-center">
                            <p class="font-bold text-black">
                                {{ formatDateTime(doc.date_sent || doc.distdate || doc.entrydate) }}
                            </p>
                        </td>

                            <td
                                v-if="canManageDts"
                                class="border border-black px-4 py-4 align-middle text-center"
                            >
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <button
                                        v-if="canManageDts && isPendingDocs07"
                                        type="button"
                                        class="inline-flex w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                        @click="pulloutPendingDocument(doc)"
                                    >
                                        Pullout
                                    </button>

                                    <button
                                        v-if="canManageDts"
                                        type="button"
                                        class="inline-flex w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                        @click="receivePendingDocument(doc)"
                                    >
                                        Receive
                                    </button>
                                </div>
                            </td>
                        </tr>

                            <tr v-if="rows.length === 0">
                                <td
                                    :colspan="canManageDts ? 6 : 5"
                                    class="border border-black px-7 py-14 text-center"
                                >
                                    <div class="text-lg font-bold text-black">
                                        {{ isPendingDocs07 ? 'No pending 07 documents found' : 'No pending documents found' }}
                                    </div>

                                    <p class="mt-2 text-sm font-medium text-black">
                                        No pending records available.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="links.length > 3"
                    class="mt-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="link in links"
                            :key="`${link.label}-${link.url}`"
                            type="button"
                            :disabled="!link.url"
                            class="rounded-lg border px-3 py-2 text-sm font-bold"
                            :class="[
                                link.active
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-blue-300 bg-white text-blue-700 hover:bg-blue-50',
                                !link.url ? 'cursor-not-allowed opacity-50' : ''
                            ]"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        ></button>
                    </div>
                </div>
            </div>

            <!-- SENT DOCS CONTENT -->
            <div
                v-else-if="activeSection === 'sent-docs'"
                class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm"
            >
                <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                            Sent Documents
                        </h2>

                        <p class="mt-2 text-sm font-medium text-black">
                            List of documents distributed or sent to offices.
                        </p>
                    </div>

                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>
                </div>

                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                :placeholder="tableSearchPlaceholder"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                @keyup.enter="runSearch"
                            />
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-400 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="runSearch"
                        >
                            Search
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-300 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="resetSearch"
                        >
                            Reset
                        </button>
                    </div>
                </div>

            <div class="overflow-x-auto rounded-xl border border-black">
                <table class="w-full min-w-[1100px] table-fixed border-collapse text-center text-sm">
                    <thead>
                        <tr class="bg-blue-600 text-white">
                            <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                DTS #
                            </th>

                            <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                Type
                            </th>

                            <th class="w-[18%] border border-black px-4 py-4 text-center font-bold">
                                To
                            </th>

                            <th class="w-[32%] border border-black px-4 py-4 text-center font-bold">
                                Subject
                            </th>

                            <th class="w-[15%] border border-black px-4 py-4 text-center font-bold">
                                Distribution<br>Date
                            </th>

                            <th class="w-[15%] border border-black px-4 py-4 text-center font-bold">
                                Return<br>Date
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(doc, index) in rows"
                            :key="doc.IDdoc"
                            :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-100'"
                        >
                            <td class="border border-black px-4 py-4 align-middle text-center">
                                <Link
                                    :href="`/dts/${doc.IDdoc}`"
                                    class="font-bold text-blue-700 hover:underline"
                                >
                                    {{ formatDtsDocumentNo(doc) }}
                                </Link>
                            </td>

                            <td class="border border-black px-4 py-4 align-middle text-center">
                                <p class="font-bold text-black">
                                    {{ doc.code || doc.abbreviation || doc.document_code || doc.doctype || doc.type || '-' }}
                                </p>
                            </td>

                            <td class="border border-black px-4 py-4 align-middle text-center">
                                <p class="whitespace-pre-line break-words font-semibold italic leading-6 text-black">
                                    {{ doc.for_office || doc.current_office || '-' }}
                                </p>
                            </td>

                            <td class="border border-black px-4 py-4 align-middle text-center">
                                <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                    {{ doc.subject || 'No subject' }}
                                </p>
                            </td>

                            <td class="border border-black px-4 py-4 align-middle text-center">
                                <p class="font-bold text-black">
                                    {{ formatDateTime(doc.distribution_date || doc.distdate || doc.date_sent || doc.entrydate) }}
                                </p>
                            </td>

                            <td class="border border-black px-4 py-4 align-middle text-center">
                                <p class="font-bold text-black">
                                    {{ formatDateTime(doc.return_date || doc.returndate, '-') }}
                                </p>
                            </td>
                        </tr>

                        <tr v-if="rows.length === 0">
                            <td colspan="6" class="border border-black px-7 py-14 text-center">
                                <div class="text-lg font-bold text-black">
                                    No sent documents found
                                </div>

                                <p class="mt-2 text-sm font-medium text-black">
                                    No sent records available.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

                <div
                    v-if="links.length > 3"
                    class="mt-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="link in links"
                            :key="`${link.label}-${link.url}`"
                            type="button"
                            :disabled="!link.url"
                            class="rounded-lg border px-3 py-2 text-sm font-bold"
                            :class="[
                                link.active
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-blue-300 bg-white text-blue-700 hover:bg-blue-50',
                                !link.url ? 'cursor-not-allowed opacity-50' : ''
                            ]"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        ></button>
                    </div>
                </div>
            </div>

                <!-- PULLED OUT DOCS CONTENT -->
                <div
                    v-else-if="activeSection === 'pulled-out-docs'"
                    class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm"
                >
                    <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                                Pulled Out Documents
                            </h2>

                            <p class="mt-2 text-sm font-medium text-black">
                                List of documents pulled out from outgoing records.
                            </p>
                        </div>

                        <div class="text-sm font-bold text-black">
                            Page {{ currentPage }} of {{ lastPage }}
                        </div>
                    </div>

                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                :placeholder="tableSearchPlaceholder"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                @keyup.enter="runSearch"
                            />
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-400 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="runSearch"
                        >
                            Search
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-300 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="resetSearch"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-black">
                    <table class="w-full min-w-[1000px] table-fixed border-collapse text-center text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                    DTS #
                                </th>

                                <th class="w-[12%] border border-black px-4 py-4 text-center font-bold">
                                    Type
                                </th>

                                <th class="w-[24%] border border-black px-4 py-4 text-center font-bold">
                                    From
                                </th>

                                <th class="w-[36%] border border-black px-4 py-4 text-center font-bold">
                                    Subject
                                </th>

                                <th class="w-[18%] border border-black px-4 py-4 text-center font-bold">
                                    Date Sent
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(doc, index) in rows"
                                :key="doc.IDdoc"
                                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-100'"
                            >
                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="font-bold text-blue-700 hover:underline"
                                    >
                                        {{ formatDtsDocumentNo(doc) }}
                                    </Link>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ doc.code || doc.abbreviation || doc.document_code || doc.doctype || doc.type || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold italic leading-6 text-black">
                                        {{ doc.from_office || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                        {{ doc.subject || 'No subject' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ formatDateTime(doc.date_sent || doc.distdate || doc.entrydate) }}
                                    </p>
                                </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                                <td colspan="5" class="border border-black px-7 py-14 text-center">
                                    <div class="text-lg font-bold text-black">
                                        No pulled out documents found
                                    </div>

                                    <p class="mt-2 text-sm font-medium text-black">
                                        No pulled out records available.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="links.length > 3"
                    class="mt-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="link in links"
                            :key="`${link.label}-${link.url}`"
                            type="button"
                            :disabled="!link.url"
                            class="rounded-lg border px-3 py-2 text-sm font-bold"
                            :class="[
                                link.active
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-blue-300 bg-white text-blue-700 hover:bg-blue-50',
                                !link.url ? 'cursor-not-allowed opacity-50' : ''
                            ]"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        ></button>
                    </div>
                </div>
            </div>
                        <!-- INCOMING TABLE CONTENT -->
            <div
                v-else-if="(activeSection === 'incoming' && !activeFilter) || activeFilter === 'for-receiving'"
                class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm"
            >
                <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                            {{ activeSection === 'incoming' && !activeFilter ? 'Incoming Documents' : 'For Receiving' }}
                        </h2>

                        <p class="mt-2 text-sm font-medium text-black">
                            {{ activeSection === 'incoming' && !activeFilter
                                ? 'List of incoming documents with current status.'
                                : 'List of documents for receiving.'
                            }}
                        </p>
                    </div>

                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>
                </div>

                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                :placeholder="tableSearchPlaceholder"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                @keyup.enter="runSearch"
                            />
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-400 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="runSearch"
                        >
                            Search
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-300 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="resetSearch"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-black">
                    <table class="w-full min-w-[1100px] table-fixed border-collapse text-center text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                    DTS #
                                </th>

                                <th class="w-[12%] border border-black px-4 py-4 text-center font-bold">
                                    Type
                                </th>

                                <th class="w-[22%] border border-black px-4 py-4 text-center font-bold">
                                    From
                                </th>

                                <th class="w-[34%] border border-black px-4 py-4 text-center font-bold">
                                    Subject
                                </th>

                                <th class="w-[14%] border border-black px-4 py-4 text-center font-bold">
                                    Date Sent
                                </th>

                                <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                    Status
                                </th>

                                <th
                                    v-if="canReceiveDts"
                                    class="w-[8%] border border-black px-4 py-4 text-center font-bold"
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(doc, index) in rows"
                                :key="doc.IDdoc"
                                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-100'"
                            >
                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="font-bold text-blue-700 hover:underline"
                                    >
                                        {{ formatDtsDocumentNo(doc) }}
                                    </Link>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ doc.code || doc.abbreviation || doc.document_code || doc.doctype || doc.type || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold italic leading-6 text-black">
                                        {{ doc.from_office || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                        {{ doc.subject || 'No subject' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ formatDateTime(doc.date_sent || doc.distdate || doc.entrydate) }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                        :class="documentStatusClass(doc)"
                                    >
                                        {{ documentStatusLabel(doc) }}
                                    </span>
                                </td>

                                <td
                                    v-if="canReceiveDts"
                                    class="border border-black px-4 py-4 align-middle text-center"
                                >
                                    <button
                                        v-if="canShowReceiveButton(doc)"
                                        type="button"
                                        class="inline-flex w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                        @click="receiveTransferredDocument(doc)"
                                    >
                                        Receive
                                    </button>

                                    <Link
                                        v-else
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="inline-flex w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                                <td :colspan="canReceiveDts ? 7 : 6" class="border border-black px-7 py-14 text-center">
                                    <div class="text-lg font-bold text-black">
                                        {{ activeSection === 'incoming' && !activeFilter
                                            ? 'No incoming documents found'
                                            : 'No documents for receiving found'
                                        }}
                                    </div>

                                    <p class="mt-2 text-sm font-medium text-black">
                                        No records available.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="links.length > 3"
                    class="mt-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="link in links"
                            :key="`${link.label}-${link.url}`"
                            type="button"
                            :disabled="!link.url"
                            class="rounded-lg border px-3 py-2 text-sm font-bold"
                            :class="[
                                link.active
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-blue-300 bg-white text-blue-700 hover:bg-blue-50',
                                !link.url ? 'cursor-not-allowed opacity-50' : ''
                            ]"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        ></button>
                    </div>
                </div>
            </div>
            <!-- INCOMING RECEIVED CONTENT -->
            <div
                v-else-if="['collab-received', 'received'].includes(activeFilter)"
                class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm"
            >
                <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                            Received
                        </h2>

                        <p class="mt-2 text-sm font-medium text-black">
                            List of received incoming documents.
                        </p>
                    </div>

                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>
                </div>

                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                :placeholder="tableSearchPlaceholder"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                @keyup.enter="runSearch"
                            />
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-400 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="runSearch"
                        >
                            Search
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-300 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="resetSearch"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-black">
                    <table class="w-full min-w-[1000px] table-fixed border-collapse text-center text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                    DTS #
                                </th>

                                <th class="w-[12%] border border-black px-4 py-4 text-center font-bold">
                                    Type
                                </th>

                                <th class="w-[24%] border border-black px-4 py-4 text-center font-bold">
                                    From
                                </th>

                                <th class="w-[36%] border border-black px-4 py-4 text-center font-bold">
                                    Subject
                                </th>

                                <th class="w-[18%] border border-black px-4 py-4 text-center font-bold">
                                    Date Sent
                                </th>

                                <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                    Status
                                </th>


                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="(doc, index) in rows"
                                :key="doc.IDdoc"
                                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-100'"
                            >
                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="font-bold text-blue-700 hover:underline"
                                    >
                                        {{ formatDtsDocumentNo(doc) }}
                                    </Link>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ doc.code || doc.abbreviation || doc.document_code || doc.doctype || doc.type || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold italic leading-6 text-black">
                                        {{ doc.from_office || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                        {{ doc.subject || 'No subject' }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ formatDateTime(doc.date_sent || doc.distdate || doc.entrydate) }}
                                    </p>
                                </td>

                                <td class="border border-black px-4 py-4 align-middle text-center">
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                        :class="documentStatusClass(doc)"
                                    >
                                        {{ documentStatusLabel(doc) }}
                                    </span>
                                </td>


                            </tr>

                            <tr v-if="rows.length === 0">
                                <td colspan="6" class="border border-black px-7 py-14 text-center">
                                    <div class="text-lg font-bold text-black">
                                        No received documents found
                                    </div>

                                    <p class="mt-2 text-sm font-medium text-black">
                                        No records available.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="links.length > 3"
                    class="mt-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="link in links"
                            :key="`${link.label}-${link.url}`"
                            type="button"
                            :disabled="!link.url"
                            class="rounded-lg border px-3 py-2 text-sm font-bold"
                            :class="[
                                link.active
                                    ? 'border-blue-600 bg-blue-600 text-white'
                                    : 'border-blue-300 bg-white text-blue-700 hover:bg-blue-50',
                                !link.url ? 'cursor-not-allowed opacity-50' : ''
                            ]"
                            @click="goToPage(link.url)"
                            v-html="link.label"
                        ></button>
                    </div>
                </div>
            </div>

            <!-- INCOMING FOR ACTION / RETURNED CONTENT -->
                <div
                    v-else-if="activeFilter === 'for-action' || activeFilter === 'returned'"
                    class="rounded-2xl border border-blue-200 bg-white p-6 shadow-sm"
                >
                    <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                                {{ activeFilter === 'for-action' ? 'For Action' : 'Returned' }}
                            </h2>

                            <p class="mt-2 text-sm font-medium text-black">
                                {{ activeFilter === 'for-action'
                                    ? 'List of documents for action.'
                                    : 'List of returned documents.'
                                }}
                            </p>
                        </div>

                        <div class="text-sm font-bold text-black">
                            Page {{ currentPage }} of {{ lastPage }}
                        </div>
                    </div>

                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto_auto] md:items-end">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                :placeholder="tableSearchPlaceholder"
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                @keyup.enter="runSearch"
                            />
                        </div>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-400 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="runSearch"
                        >
                            Search
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-blue-300 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100"
                            @click="resetSearch"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                    <div class="overflow-x-auto rounded-xl border border-black">
                        <table class="w-full min-w-[1000px] table-fixed border-collapse text-center text-sm">
                            <thead>
                                <tr class="bg-blue-600 text-white">
                                    <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                        DTS #
                                    </th>

                                    <th class="w-[12%] border border-black px-4 py-4 text-center font-bold">
                                        Type
                                    </th>

                                    <th class="w-[24%] border border-black px-4 py-4 text-center font-bold">
                                        From
                                    </th>

                                    <th class="w-[36%] border border-black px-4 py-4 text-center font-bold">
                                        Subject
                                    </th>

                                    <th class="w-[18%] border border-black px-4 py-4 text-center font-bold">
                                        Date Sent
                                    </th>

                                    <th class="w-[10%] border border-black px-4 py-4 text-center font-bold">
                                        Status
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(doc, index) in rows"
                                    :key="doc.IDdoc"
                                    :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-100'"
                                >
                                    <td class="border border-black px-4 py-4 align-middle text-center">
                                        <Link
                                            :href="`/dts/${doc.IDdoc}`"
                                            class="font-bold text-blue-700 hover:underline"
                                        >
                                            {{ formatDtsDocumentNo(doc) }}
                                        </Link>
                                    </td>

                                    <td class="border border-black px-4 py-4 align-middle text-center">
                                        <p class="font-bold text-black">
                                            {{ doc.code || doc.abbreviation || doc.document_code || doc.doctype || doc.type || '-' }}
                                        </p>
                                    </td>

                                    <td class="border border-black px-4 py-4 align-middle text-center">
                                        <p class="whitespace-pre-line break-words font-semibold italic leading-6 text-black">
                                            {{ doc.from_office || '-' }}
                                        </p>
                                    </td>

                                    <td class="border border-black px-4 py-4 align-middle text-center">
                                        <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                            {{ doc.subject || 'No subject' }}
                                        </p>
                                    </td>

                                    <td class="border border-black px-4 py-4 align-middle text-center">
                                        <p class="font-bold text-black">
                                            {{ formatDateTime(doc.date_sent || doc.distdate || doc.entrydate) }}
                                        </p>
                                    </td>

                                    <td class="border border-black px-4 py-4 align-middle text-center">
                                        <span
                                            class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                            :class="documentStatusClass(doc)"
                                        >
                                            {{ documentStatusLabel(doc) }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="rows.length === 0">
                                    <td colspan="6" class="border border-black px-7 py-14 text-center">
                                        <div class="text-lg font-bold text-black">
                                            {{ activeFilter === 'for-action'
                                                ? 'No for action documents found'
                                                : 'No returned documents found'
                                            }}
                                        </div>

                                        <p class="mt-2 text-sm font-medium text-black">
                                            No records available.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="links.length > 3"
                        class="mt-5 flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="text-sm font-bold text-black">
                            Page {{ currentPage }} of {{ lastPage }}
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="link in links"
                                :key="`${link.label}-${link.url}`"
                                type="button"
                                :disabled="!link.url"
                                class="rounded-lg border px-3 py-2 text-sm font-bold"
                                :class="[
                                    link.active
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-blue-300 bg-white text-blue-700 hover:bg-blue-50',
                                    !link.url ? 'cursor-not-allowed opacity-50' : ''
                                ]"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                            ></button>
                        </div>
                    </div>
                </div>




            <!-- DOCUMENTS / SEARCH CONTENT -->
            <template v-else>
                <!-- Stats Cards -->
                <div
                    v-if="activeSection === 'documents'"
                    class="mb-8 grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5"
                >
                    <Link
                        :href="buildDtsUrl({})"
                        class="group relative min-h-[150px] overflow-hidden rounded-[1.8rem] bg-gradient-to-br from-blue-600 to-indigo-600 p-6 text-white shadow-xl shadow-blue-100 transition hover:-translate-y-1 hover:shadow-2xl"
                    >
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-5">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl backdrop-blur">
                                📄
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-base font-black text-white/90">
                                        Total Documents
                                    </p>

                                    
                                </div>

                                <p class="mt-3 text-5xl font-black leading-none tracking-tight">
                                    {{ props.stats.total }}
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="buildDtsUrl({ section: 'incoming', filter: 'for-receiving' })"
                        class="group relative min-h-[150px] overflow-hidden rounded-[1.8rem] bg-gradient-to-br from-violet-600 to-fuchsia-600 p-6 text-white shadow-xl shadow-violet-100 transition hover:-translate-y-1 hover:shadow-2xl"
                    >
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-5">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl backdrop-blur">
                                ⏳
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-base font-black text-white/90">
                                        For Receiving
                                    </p>
                                </div>

                                <p class="mt-3 text-5xl font-black leading-none tracking-tight">
                                    {{ props.stats.for_receiving }}
                                </p>

                                <p class="mt-3 text-sm font-semibold text-white/75">
                                    Click to view pending receiving
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="buildDtsUrl({ section: 'incoming', filter: 'received' })"
                        class="group relative min-h-[150px] overflow-hidden rounded-[1.8rem] bg-gradient-to-br from-emerald-600 to-green-500 p-6 text-white shadow-xl shadow-emerald-100 transition hover:-translate-y-1 hover:shadow-2xl"
                    >
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-5">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl backdrop-blur">
                                ✅
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-base font-black text-white/90">
                                        Received
                                    </p>
                                </div>

                                <p class="mt-3 text-5xl font-black leading-none tracking-tight">
                                    {{ props.stats.received }}
                                </p>

                                <p class="mt-3 text-sm font-semibold text-white/75">
                                    Received, waiting for Select Action
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="buildDtsUrl({ section: 'addressed-docs' })"
                        class="group relative min-h-[150px] overflow-hidden rounded-[1.8rem] bg-gradient-to-br from-cyan-600 to-sky-500 p-6 text-white shadow-xl shadow-cyan-100 transition hover:-translate-y-1 hover:shadow-2xl"
                    >
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-5">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl backdrop-blur">
                                📌
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-base font-black text-white/90">
                                        Addressed
                                    </p>
                                </div>

                                <p class="mt-3 text-5xl font-black leading-none tracking-tight">
                                    {{ addressedCount }}
                                </p>

                                <p class="mt-3 text-sm font-semibold text-white/75">
                                    Received documents with Selected Action
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="buildDtsUrl({ section: 'incoming', filter: 'returned' })"
                        class="group relative min-h-[150px] overflow-hidden rounded-[1.8rem] bg-gradient-to-br from-rose-600 to-pink-500 p-6 text-white shadow-xl shadow-rose-100 transition hover:-translate-y-1 hover:shadow-2xl"
                    >
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-5">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-2xl backdrop-blur">
                                ↩️
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-base font-black text-white/90">
                                        Returned
                                    </p>
                                </div>

                                <p class="mt-3 text-5xl font-black leading-none tracking-tight">
                                    {{ props.stats.returned }}
                                </p>

                                <p class="mt-3 text-sm font-semibold text-white/75">
                                    Click to view returned records
                                </p>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Search -->
                <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">
                                Search Documents
                            </h2>

                            <p class="mt-1 text-sm text-slate-500">
                                {{ tableSearchDescription }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <span class="font-medium">
                                Show
                            </span>

                            <div class="relative">
                                <select
                                    v-model="perPage"
                                    class="h-11 w-24 appearance-none rounded-xl border border-slate-300 bg-white px-4 pr-10 text-sm font-semibold text-slate-700 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    @change="applyFilters"
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

                            <span class="font-medium">
                                entries
                            </span>
                        </div>
                    </div>

                    <form
                        class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto_auto]"
                        @submit.prevent="runSearch"
                    >
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="tableSearchPlaceholder"
                            class="w-full rounded-xl border border-slate-300 px-5 py-3.5 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />

                        <button
                            type="submit"
                            class="rounded-xl bg-blue-600 px-7 py-3.5 text-sm font-semibold text-white hover:bg-blue-700"
                        >
                            Search
                        </button>

                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-7 py-3.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="resetSearch"
                        >
                            Reset
                        </button>
                    </form>
                </div>

                <!-- Document List -->
                <div class="rounded-2xl border border-blue-600 bg-white shadow-sm">
                    <div class="rounded-t-2xl border-b border-blue-700 bg-blue-600 px-7 py-5">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-white">
                                    {{ isAllDocumentsSection
                                        ? 'All Documents List'
                                        : (isAddressedDocumentsSection ? 'Addressed Documents List' : 'Document List')
                                    }}
                                </h2>

                                <p class="mt-1 text-sm text-white">
                                    {{ isAllDocumentsSection
                                        ? 'Complete registry for Role 2. Non-tagged documents are available for viewing only.'
                                        : (isAddressedDocumentsSection
                                            ? 'Documents that were received and already have Select Action.'
                                            : 'Latest document records from the DTS database.')
                                    }}
                                </p>
                            </div>

                            <div class="text-sm text-white">
                                Showing
                                <span class="font-semibold text-white">
                                    {{ paginationFrom }}
                                </span>
                                to
                                <span class="font-semibold text-white">
                                    {{ paginationTo }}
                                </span>
                                of
                                <span class="font-semibold text-white">
                                    {{ paginationTotal }}
                                </span>
                                entries
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1000px] table-fixed text-left text-sm">
                            <thead class="bg-slate-50 text-slate-700">
                                <tr>
                                    <th class="w-[10%] border-b border-slate-200 px-4 py-4 font-bold">
                                        DOC ID
                                    </th>

                                    <th class="w-[22%] border-b border-slate-200 px-4 py-4 font-bold">
                                        TO
                                    </th>

                                    <th class="w-[30%] border-b border-slate-200 px-4 py-4 font-bold">
                                        SUBJECT
                                    </th>

                                    <th class="w-[16%] border-b border-slate-200 px-4 py-4 font-bold">
                                        DATE SEND
                                    </th>

                                    <th class="w-[12%] border-b border-slate-200 px-4 py-4 text-center font-bold">
                                        {{ isAddressedDocumentsSection ? 'SELECTED ACTION' : 'STATUS' }}
                                    </th>

                                    <th class="w-[10%] border-b border-slate-200 px-4 py-4 text-center font-bold">
                                        ACTION
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="doc in rows"
                                    :key="doc.IDdoc"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-5 align-top">
                                        <span class="font-bold text-blue-700">
                                            {{ formatDtsDocumentNo(doc) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-5 align-top">
                                        <div class="whitespace-normal break-words text-sm font-bold leading-6 text-slate-800">
                                            {{ doc.to_personnel || doc.receiver_personnel || doc.personnel_name || doc.staff_concern || doc.current_office || doc.for_office || '-' }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-5 align-top">
                                        <div class="whitespace-normal break-words text-sm font-semibold leading-6 text-slate-800">
                                            {{ doc.subject || 'No subject' }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-5 align-top text-slate-700">
                                        <div class="whitespace-normal break-words text-sm font-semibold leading-6">
                                            {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                        </div>
                                    </td>

                                    <td class="px-4 py-5 text-center align-top">
                                        <span
                                            class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                            :class="isAddressedDocumentsSection ? selectedActionClass(doc) : documentStatusClass(doc)"
                                        >
                                            {{ isAddressedDocumentsSection ? selectedActionLabel(doc) : documentStatusLabel(doc) }}
                                        </span>

                                        <p
                                            v-if="isAddressedDocumentsSection && doc.selected_action_date"
                                            class="mt-2 text-[11px] font-semibold text-slate-500"
                                        >
                                            {{ formatDateTime(doc.selected_action_date) }}
                                        </p>
                                    </td>

                                    <td class="px-4 py-5 text-center align-top">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <Link
                                                :href="`/dts/${doc.IDdoc}`"
                                                class="inline-flex w-16 justify-center rounded-lg border border-blue-600 px-3 py-2 text-xs font-semibold text-blue-600 hover:bg-blue-50"
                                            >
                                                View
                                            </Link>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="rows.length === 0">
                                    <td colspan="6" class="px-7 py-14 text-center">
                                        <div class="text-lg font-semibold text-slate-700">
                                            No documents found
                                        </div>

                                        <p class="mt-2 text-sm text-slate-500">
                                            Try another keyword or click Reset.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="links.length > 3"
                        class="flex flex-col gap-4 border-t border-slate-200 px-7 py-5 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="text-sm text-slate-500">
                            Page
                            <span class="font-semibold text-slate-700">
                                {{ currentPage }}
                            </span>
                            of
                            <span class="font-semibold text-slate-700">
                                {{ lastPage }}
                            </span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="link in links"
                                :key="`${link.label}-${link.url}`"
                                type="button"
                                :disabled="!link.url"
                                class="rounded-lg border px-3 py-2 text-sm font-semibold"
                                :class="[
                                    link.active
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                                    !link.url ? 'cursor-not-allowed opacity-50' : ''
                                ]"
                                @click="goToPage(link.url)"
                                v-html="link.label"
                            ></button>
                        </div>
                    </div>
                </div>
            </template>
        </main>

        <!-- Pending Docs 07 Action Confirmation Modal -->
        <div
            v-if="canManageDts && showPendingActionModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 py-8"
        >
            <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="border-b border-blue-100 bg-blue-600 px-6 py-5">
                    <h2 class="text-xl font-bold text-white">
                        Confirm Action
                    </h2>

                    <p class="mt-1 text-sm font-medium text-blue-50">
                        Please confirm before continuing.
                    </p>
                </div>

                <div class="p-6">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <p class="text-sm font-bold text-blue-700">
                            Document ID
                        </p>

                        <p class="mt-1 text-lg font-bold text-black">
                            {{ formatDtsDocumentNo(selectedPendingDocument || {}) }}
                        </p>

                        <p class="mt-4 text-sm font-bold text-blue-700">
                            Subject
                        </p>

                        <p class="mt-1 break-words text-sm font-semibold leading-6 text-black">
                            {{ selectedPendingDocument?.subject || 'No subject' }}
                        </p>
                    </div>

                    <p class="mt-5 text-sm font-semibold leading-6 text-black">
                        Are you sure you want to
                        <span
                            class="font-bold"
                            :class="pendingActionType === 'receive' ? 'text-green-700' : 'text-red-700'"
                        >
                            {{ pendingActionType === 'receive' ? 'receive' : 'pull out' }}
                        </span>
                        this document?
                    </p>

                    <div
                        v-if="pendingActionType === 'receive'"
                        class="mt-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-800"
                    >
                        This will set the document as received and add confirmation date/time.
                    </div>

                    <div
                        v-if="pendingActionType === 'pullout'"
                        class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-800"
                    >
                        This will mark the document as pulled out.
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-blue-100 bg-blue-50 px-6 py-4">
                    <button
                        type="button"
                        class="rounded-xl border border-blue-300 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100 disabled:opacity-60"
                        :disabled="pendingActionProcessing"
                        @click="closePendingActionModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-xl px-5 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                        :class="pendingActionType === 'receive'
                            ? 'bg-green-600 hover:bg-green-700'
                            : 'bg-red-600 hover:bg-red-700'"
                        :disabled="pendingActionProcessing"
                        @click="confirmPendingAction"
                    >
                        {{
                            pendingActionProcessing
                                ? 'Processing...'
                                : pendingActionType === 'receive'
                                    ? 'Yes, Receive'
                                    : 'Yes, Pullout'
                        }}
                    </button>
                </div>
            </div>
        </div>

        <AddDocumentModal
            v-if="canManageDts"
            :show="showAddDocumentModal"
            :offices="props.offices"
            :doc-types="props.docTypes"
            :classifications="props.classifications"
            :attachments="props.attachments"
            :staff-concerns="props.staffConcerns"
            @close="closeAddDocumentModal"
        />

        <!-- Edit Entry Date Modal -->
        <div
            v-if="canManageDts && showEditEntryDateModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 px-4 py-8"
        >
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">
                            Edit Entry Date
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            DTS #: {{ formatDtsId(selectedDocument?.IDdoc) }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100"
                        @click="closeEditEntryDateModal"
                    >
                        ✕
                    </button>
                </div>

                <form class="space-y-5 p-6" @submit.prevent="submitEntryDateUpdate">
                    <div>
                        <label class="mb-1 block text-sm font-bold text-slate-700">
                            Entry Date
                        </label>

                        <input
                            v-model="entryDateForm.entrydate"
                            type="datetime-local"
                            class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />

                        <p
                            v-if="entryDateForm.errors.entrydate"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ entryDateForm.errors.entrydate }}
                        </p>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="closeEditEntryDateModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="entryDateForm.processing"
                        >
                            Save Date
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </DTSLayout>
</template>

<style>

.print-report-table {
    display: none;
}
@media print {
    body * {
        visibility: hidden !important;
    }

    .report-print-area,
    .report-print-area * {
        visibility: visible !important;
    }

    .report-print-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        border: none !important;
        box-shadow: none !important;
    }

    .no-print {
        display: none !important;
    }

    .screen-report-table {
        display: none !important;
    }

    .print-report-table {
        display: table !important;
        width: 100% !important;
        min-width: 0 !important;
        font-size: 10px !important;
    }

    .print-report-table th,
    .print-report-table td {
        padding: 6px !important;
        vertical-align: top !important;
    }

    @page {
        size: landscape;
        margin: 12mm;
    }
}
</style>
