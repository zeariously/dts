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
            for_action: 0,
            in_progress: 0,
            addressed: 0,
            completed: 0,
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
    nextDocumentId: {
        type: [Number, String],
        default: null,
    },
    viewerNotifications: {
        type: Array,
        default: () => [],
    },
    creatorReceivedNotifications: {
        type: Array,
        default: () => [],
    },
    automaticStatusReminders: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()

const userRights = computed(() => {
    return String(page.props.auth?.user?.rights ?? '').trim()
})

const canShowReturnedCard = computed(() => {
    /*
     * Returned card is visible only to Role 3.
     * It contains documents returned by Role 2 accounts.
     */
    return userRights.value === '3'
})

const canShowCompletedCard = computed(() => {
    return false
})

const currentUserId = computed(() => {
    return String(page.props.auth?.user?.ID ?? page.props.auth?.user?.id ?? '').trim()
})

const currentPersonnelIds = computed(() => {
    return [
        page.props.auth?.user?.idmapagency,
        page.props.auth?.user?.IDmapagency,
        page.props.auth?.user?.IDmapAgency,
        page.props.auth?.user?.IDpersonnel,
        page.props.auth?.user?.personnel_id,
        page.props.auth?.user?.IDkeeper,
    ]
        .filter((id) => id !== undefined && id !== null && String(id).trim() !== '' && Number(id) !== 0)
        .map((id) => String(id).trim())
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


/*
 * Separate automatic 3-day reminder modal.
 * This does not change or consume the notification bell items.
 */
const showAutomaticReminderModal = ref(false)

/*
 * Reminder sound:
 * - Plays a short three-tone chime whenever the automatic reminder opens.
 * - Uses Web Audio, so no MP3/WAV file is required.
 * - If the browser blocks autoplay, it plays after the user's first click or
 *   keypress while the reminder modal is still open.
 */
let automaticReminderAudioContext = null
let automaticReminderSoundPending = false
let automaticReminderSoundPlayedForCurrentOpen = false

const getAutomaticReminderAudioContext = () => {
    if (typeof window === 'undefined') return null

    const AudioContextClass = window.AudioContext || window.webkitAudioContext

    if (!AudioContextClass) return null

    if (!automaticReminderAudioContext) {
        automaticReminderAudioContext = new AudioContextClass()
    }

    return automaticReminderAudioContext
}

const playAutomaticReminderSound = async () => {
    if (automaticReminderSoundPlayedForCurrentOpen) return

    const audioContext = getAutomaticReminderAudioContext()

    if (!audioContext) return

    try {
        if (audioContext.state === 'suspended') {
            await audioContext.resume()
        }

        const startAt = audioContext.currentTime + 0.03
        const notes = [659.25, 783.99, 987.77]

        notes.forEach((frequency, index) => {
            const oscillator = audioContext.createOscillator()
            const gain = audioContext.createGain()
            const noteStart = startAt + (index * 0.16)
            const noteEnd = noteStart + 0.13

            oscillator.type = 'sine'
            oscillator.frequency.setValueAtTime(frequency, noteStart)

            gain.gain.setValueAtTime(0.0001, noteStart)
            gain.gain.exponentialRampToValueAtTime(0.16, noteStart + 0.02)
            gain.gain.exponentialRampToValueAtTime(0.0001, noteEnd)

            oscillator.connect(gain)
            gain.connect(audioContext.destination)
            oscillator.start(noteStart)
            oscillator.stop(noteEnd + 0.02)
        })

        automaticReminderSoundPlayedForCurrentOpen = true
        automaticReminderSoundPending = false
    } catch (error) {
        automaticReminderSoundPending = true
    }
}

const unlockAutomaticReminderSound = async () => {
    const audioContext = getAutomaticReminderAudioContext()

    if (!audioContext) return

    try {
        if (audioContext.state === 'suspended') {
            await audioContext.resume()
        }

        if (
            automaticReminderSoundPending
            && showAutomaticReminderModal.value
            && hasAutomaticStatusReminders.value
        ) {
            await playAutomaticReminderSound()
        }
    } catch (error) {
        // The next user interaction can try again.
    }
}

const automaticReminderItems = computed(() => {
    return props.automaticStatusReminders || []
})

const hasAutomaticStatusReminders = computed(() => {
    return automaticReminderItems.value.length > 0
})

const automaticReminderCount = computed(() => {
    return automaticReminderItems.value.length
})

const automaticReminderStatusCount = (status) => {
    return automaticReminderItems.value.filter((item) => {
        return String(item?.current_status || '').trim().toLowerCase()
            === String(status || '').trim().toLowerCase()
    }).length
}

const formatPendingDays = (value) => {
    const days = Number(value)

    if (!Number.isFinite(days) || days < 0) {
        return 0
    }

    return Math.floor(days)
}

const automaticReminderBadgeClass = (status) => {
    const value = String(status || '').trim().toLowerCase()

    if (['for receiving', 'received', 'in progress'].includes(value)) {
        return 'border-red-300 bg-red-100 text-red-800'
    }

    return 'border-red-200 bg-red-50 text-red-700'
}

const openAutomaticReminderModal = () => {
    if (!hasAutomaticStatusReminders.value) return

    const wasClosed = !showAutomaticReminderModal.value

    showAutomaticReminderModal.value = true

    if (wasClosed) {
        automaticReminderSoundPlayedForCurrentOpen = false
        automaticReminderSoundPending = true
        playAutomaticReminderSound()
    }
}

const closeAutomaticReminderModal = () => {
    /*
     * Disregard only closes the current modal.
     * Nothing is saved in localStorage, so unresolved reminders will prompt
     * again on refresh, navigation, or the next dashboard visit.
     */
    showAutomaticReminderModal.value = false
    automaticReminderSoundPending = false
    automaticReminderSoundPlayedForCurrentOpen = false
}

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

    if (typeof window !== 'undefined') {
        window.addEventListener('pointerdown', unlockAutomaticReminderSound)
        window.addEventListener('keydown', unlockAutomaticReminderSound)
    }

    /*
     * Always prompt whenever the dashboard loads and an unresolved 3-day
     * reminder exists. Disregarding it does not mark it as seen.
     */
    if (hasAutomaticStatusReminders.value) {
        openAutomaticReminderModal()
    }
})

onBeforeUnmount(() => {
    clearTimeout(automaticReportLoadTimer)

    if (typeof window !== 'undefined') {
        window.removeEventListener('pointerdown', unlockAutomaticReminderSound)
        window.removeEventListener('keydown', unlockAutomaticReminderSound)
    }

    if (automaticReminderAudioContext) {
        automaticReminderAudioContext.close().catch(() => {})
        automaticReminderAudioContext = null
    }
})

watch(
    automaticReminderItems,
    () => {
        /* Re-open after an Inertia refresh when reminders still exist. */
        if (hasAutomaticStatusReminders.value) {
            openAutomaticReminderModal()
        } else {
            showAutomaticReminderModal.value = false
        }
    },
    { deep: true }
)

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

const forReceivingNotifications = computed(() => {
    return transferNotifications.value.filter((item) => item.notification_type !== 'received_by_addressee')
})

const overdueTransferNotifications = computed(() => {
    return forReceivingNotifications.value.filter((item) => item.is_overdue)
})

const openNotificationsFromBell = () => {
    showTransferNotificationModal.value = true
}

const closeTransferNotificationModal = () => {
    showTransferNotificationModal.value = false
}

watch(
    transferNotifications,
    () => {
        /*
         * Do not auto-open the notification modal.
         * Notifications should stay in the bell and open only when the user clicks it.
         */
        showTransferNotificationModal.value = false
    },
    { immediate: false }
)



const search = ref(props.filters?.search || '')
const perPage = ref(Number(props.filters?.per_page || 10))

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

const activeFilter = computed(() => {
    return currentParams.value.get('filter') || ''
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

const buildCurrentPayload = () => {
    const payload = {
        per_page: perPage.value,
    }

    if (activeSection.value !== 'reports') {
        if (selectedYear.value === 'all') {
            payload.year = 'all'
        } else if (selectedYear.value) {
            payload.year = selectedYear.value
        }
    }

    if (activeSection.value !== 'documents') {
        payload.section = activeSection.value
    }

    if (activeSection.value === 'all-documents') {
        payload.scope = 'all'
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

        if (reportUser.value) {
            payload.report_user = reportUser.value
        }

        if (reportStartDate.value) {
            payload.start_date = reportStartDate.value
        }

        if (reportEndDate.value) {
            payload.end_date = reportEndDate.value
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
const reportUser = ref(currentParams.value.get('report_user') || '')

const isoToDisplayDate = (value) => {
    const match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/)

    if (!match) return ''

    return `${match[3]}/${match[2]}/${match[1]}`
}

const displayToIsoDate = (value) => {
    const match = String(value || '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/)

    if (!match) return ''

    const day = Number(match[1])
    const month = Number(match[2])
    const year = Number(match[3])
    const date = new Date(year, month - 1, day)

    if (
        date.getFullYear() !== year
        || date.getMonth() !== month - 1
        || date.getDate() !== day
    ) {
        return ''
    }

    return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

const maskReportDateInput = (value) => {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 8)

    if (digits.length <= 2) return digits
    if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`
}

const initialReportStartDate = currentParams.value.get('start_date') || ''
const initialReportEndDate = currentParams.value.get('end_date') || ''

const reportStartDate = ref(initialReportStartDate)
const reportEndDate = ref(initialReportEndDate)
const reportStartDateDisplay = ref(isoToDisplayDate(initialReportStartDate))
const reportEndDateDisplay = ref(isoToDisplayDate(initialReportEndDate))
const reportErrors = ref({})
let automaticReportLoadTimer = null

const updateReportDate = (field, value) => {
    const maskedValue = maskReportDateInput(value)

    if (field === 'start') {
        reportStartDateDisplay.value = maskedValue
        reportStartDate.value = displayToIsoDate(maskedValue)
    } else {
        reportEndDateDisplay.value = maskedValue
        reportEndDate.value = displayToIsoDate(maskedValue)
    }

    /*
     * Automatically reload only when the field is empty or already contains
     * a complete valid DD/MM/YYYY date.
     */
    const normalizedDate = field === 'start'
        ? reportStartDate.value
        : reportEndDate.value

    if (maskedValue === '' || normalizedDate) {
        scheduleAutomaticReportLoad()
    }
}

const reportUserOptions = computed(() => {
    return [...(props.staffConcerns || [])]
        .filter((user) => {
            const id = user?.ID ?? user?.id
            const name = user?.name ?? user?.full_name

            return id !== undefined
                && id !== null
                && String(id).trim() !== ''
                && String(name || '').trim() !== ''
        })
        .sort((first, second) => {
            return String(first?.name || first?.full_name || '')
                .localeCompare(String(second?.name || second?.full_name || ''))
        })
})

const reportUserOptionId = (user) => {
    return String(user?.ID ?? user?.id ?? '')
}

const reportUserOptionLabel = (user) => {
    const name = user?.name || user?.full_name || 'Unnamed User'
    const office = user?.office_name || user?.officename || ''

    return office ? `${name} — ${office}` : name
}

const selectedReportUserLabel = computed(() => {
    const selectedUser = reportUserOptions.value.find((user) => {
        return reportUserOptionId(user) === String(reportUser.value || '')
    })

    return selectedUser ? reportUserOptionLabel(selectedUser) : 'All Users'
})

const validateReportFilters = () => {
    reportErrors.value = {}

    /*
     * Empty Classification and User mean "All".
     * The report is allowed to load even without a date range.
     */
    if (reportStartDateDisplay.value && !reportStartDate.value) {
        reportErrors.value.start_date = 'Start Date must use DD/MM/YYYY and contain a valid date.'
    }

    if (reportEndDateDisplay.value && !reportEndDate.value) {
        reportErrors.value.end_date = 'End Date must use DD/MM/YYYY and contain a valid date.'
    }

    if (reportStartDate.value && reportEndDate.value) {
        const start = new Date(`${reportStartDate.value}T00:00:00`)
        const end = new Date(`${reportEndDate.value}T00:00:00`)

        if (start > end) {
            reportErrors.value.date = 'End date must be equal to or later than start date.'
        }
    }

    return Object.keys(reportErrors.value).length === 0
}

const loadReportAutomatically = () => {
    if (!validateReportFilters()) {
        return
    }

    router.get('/dts', {
        section: 'reports',
        report_classification: reportClassification.value,
        report_user: reportUser.value,
        start_date: reportStartDate.value,
        end_date: reportEndDate.value,
        per_page: perPage.value,
    }, {
        preserveScroll: true,
        replace: true,
    })
}

const scheduleAutomaticReportLoad = () => {
    clearTimeout(automaticReportLoadTimer)

    automaticReportLoadTimer = window.setTimeout(() => {
        loadReportAutomatically()
    }, 350)
}

const pageTitle = computed(() => {
    if (activeSection.value === 'all-documents') return 'All Documents'
    if (activeSection.value === 'search') return 'Search'
    if (activeSection.value === 'reports') return 'Reports'
    if (activeSection.value === 'about') return 'About'
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
    if (activeFilter.value === 'for-action') return 'Received'
    if (['in-progress', 'addressed', 'completed'].includes(activeFilter.value)) return 'Addressed'
    if (activeFilter.value === 'returned') return 'Returned'

    return 'Documents'
})

const isPendingDocs07 = computed(() => {
    return activeSection.value === 'pending-docs-07'
})

const incomingSections = ['incoming', 'received-docs', 'pending-docs', 'pending-docs-07']
const outgoingSections = ['outgoing', 'sent-docs', 'pulled-out-docs']
const collaborationFilters = ['for-receiving', 'received', 'collab-received', 'for-action', 'in-progress', 'addressed', 'returned']

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

    if (params.section === 'all-documents') {
        query.set('scope', 'all')
    }

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
            active: ['received', 'collab-received', 'for-action'].includes(activeFilter.value),
            count: props.stats.received ?? 0,
        },
        {
            label: 'Addressed',
            href: buildDtsUrl({ section: 'incoming', filter: 'addressed' }),
            active: ['in-progress', 'addressed', 'completed'].includes(activeFilter.value),
            count: props.stats.addressed ?? props.stats.in_progress ?? 0,
        },
        ...(canShowReturnedCard.value ? [{
            label: 'Returned',
            href: buildDtsUrl({ section: 'incoming', filter: 'returned' }),
            active: activeFilter.value === 'returned',
            count: props.stats.returned ?? 0,
        }] : []),
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
            active: ['received', 'collab-received', 'for-action'].includes(activeFilter.value),
            count: props.stats.received,
        },
        {
            label: 'Addressed',
            href: buildDtsUrl({ section: 'incoming', filter: 'addressed' }),
            active: ['in-progress', 'addressed', 'completed'].includes(activeFilter.value),
            count: props.stats.addressed ?? props.stats.in_progress ?? 0,
        },
        ...(canShowReturnedCard.value ? [{
            label: 'Returned',
            href: buildDtsUrl({ section: 'incoming', filter: 'returned' }),
            active: activeFilter.value === 'returned',
            count: props.stats.returned,
        }] : []),
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

const isSameId = (first, second) => {
    return String(first ?? '').trim() !== ''
        && String(first ?? '').trim() === String(second ?? '').trim()
}

const isTaggedToCurrentPersonnel = (doc) => {
    if (!currentPersonnelIds.value.length) {
        return false
    }

    return [
        doc?.IDkeeper,
        doc?.distribution_personnel_id,
        doc?.returned_to_personnel_id,
    ].some((id) => currentPersonnelIds.value.includes(String(id ?? '').trim()))
}

const shouldHideReturnedAwayFromRoleTwo = (doc) => {
    /*
     * Frontend safety:
     * Role 2 normal Dashboard list:
     * hide documents already returned away to the encoder.
     *
     * IMPORTANT:
     * Do NOT apply this safety filter in All Documents.
     * Old working code displayed props.documents directly, so All Documents
     * must not do any client-side tag filtering.
     */
    if (isAllDocumentsSection.value) {
        return false
    }

    if (userRights.value !== '2') {
        return false
    }

    if (activeFilter.value === 'returned') {
        return false
    }

    if (!isSameId(doc?.returned_by, currentUserId.value)) {
        return false
    }

    return !isTaggedToCurrentPersonnel(doc)
}

const rows = computed(() => {
    const sourceRows = Array.isArray(props.documents)
        ? props.documents
        : (props.documents?.data || [])

    /*
     * All Documents must display the full backend result.
     * This matches the old working code where rows returned props.documents.data
     * directly without client-side tag filtering.
     */
    if (isAllDocumentsSection.value) {
        return sourceRows
    }

    return sourceRows.filter((doc) => !shouldHideReturnedAwayFromRoleTwo(doc))
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

const resetSearch = () => {
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

    if (activeSection.value === 'all-documents') {
        payload.scope = 'all'
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



const documentHasSelectedAction = (doc) => {
    const actionType = String(doc?.action_type || '').trim().toLowerCase()

    return doc?.has_selected_action === true
        || doc?.has_selected_action === 1
        || doc?.has_selected_action === '1'
        || actionType === 'action_taken'
}

const isDatabaseAddressedDocument = (doc) => {
    const statusId = Number(
        doc?.IDdocstatus
        ?? doc?.document_status_id
        ?? doc?.status_id
        ?? 0
    )

    const statusText = String(
        doc?.workflow_status
        || doc?.status_label
        || doc?.status
        || ''
    ).trim().toLowerCase()

    return statusId === 6
        || doc?.is_completed === true
        || doc?.is_completed === 1
        || doc?.is_completed === '1'
        || Boolean(doc?.completed_at)
        || Boolean(doc?.datecleared)
        || statusText.includes('completed')
        || statusText.includes('complete')
        || statusText.includes('cleared')
}

const documentStatusLabel = (doc) => {
    if (
        ['in-progress', 'addressed', 'completed'].includes(activeFilter.value)
        || ['addressed-docs', 'completed-docs'].includes(activeSection.value)
    ) {
        return 'Addressed'
    }

    /*
     * Database is the source of truth:
     * If the document table says completed/addressed, display Addressed
     * even when workflow_status/status_label is still Pending or For Receiving.
     */
    if (isDatabaseAddressedDocument(doc) || documentHasSelectedAction(doc)) {
        return 'Addressed'
    }

    const status = doc.workflow_status
        || doc.status_label
        || doc.status
        || '-'

    const statusText = String(status || '').trim().toLowerCase()

    /* No real Address action yet: do not display Addressed accidentally. */
    if (!documentHasSelectedAction(doc)) {
        if (
            statusText.includes('done')
            || statusText.includes('addressed')
            || statusText.includes('action taken')
        ) {
            if (doc?.confirmdate || doc?.date_received || doc?.received_date) {
                return 'Received'
            }

            if (doc?.distdate || doc?.distribution_date || doc?.date_sent) {
                return 'For Receiving'
            }
        }
    }

    return status
}

const documentStatusClass = (doc) => {
    const status = String(documentStatusLabel(doc)).toLowerCase()

    if (
        status.includes('addressed')
        || status.includes('in progress')
        || status.includes('completed')
        || status.includes('complete')
        || status.includes('cleared')
        || status === 'done'
        || status.includes('done')
        || status.includes('approved')
    ) {
        return 'border-cyan-300 bg-cyan-100 text-cyan-800'
    }

    if (status === 'received' || status.includes('received')) {
        return 'border-emerald-300 bg-emerald-100 text-emerald-800'
    }

    if (status === 'for receiving' || status.includes('for receiving')) {
        return 'border-violet-300 bg-violet-100 text-violet-800'
    }

    if (status.includes('pending 07')) {
        return 'border-orange-300 bg-orange-100 text-orange-800'
    }

    if (status.includes('pending')) {
        return 'border-amber-300 bg-amber-100 text-amber-900'
    }

    if (status.includes('return')) {
        return 'border-rose-300 bg-rose-100 text-rose-800'
    }

    if (status.includes('pulled')) {
        return 'border-slate-300 bg-slate-100 text-slate-800'
    }

    return 'border-slate-300 bg-slate-100 text-slate-700'
}

const returnedByDisplay = (doc) => {
    return doc?.returned_by_name
        || doc?.returned_by
        || doc?.return_by_name
        || doc?.return_user_name
        || ''
}

const shouldShowReturnedBy = (doc) => {
    /*
     * Show Returned By only inside the Returned list/card.
     * Do not show this in the normal document list.
     */
    return activeFilter.value === 'returned'
        && Boolean(String(returnedByDisplay(doc) || '').trim())
}



const documentToDisplay = (doc) => {
    return doc?.to_personnel
        || doc?.receiver_personnel
        || doc?.personnel_name
        || doc?.staff_concern
        || doc?.current_office
        || doc?.for_office
        || doc?.to_office
        || doc?.transferred_to
        || doc?.received_office
        || '-'
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

const printReport = () => {
    const originalTitle = document.title

    /*
     * Temporarily clear the page title so the browser does not include
     * "Document Tracking System - Laravel" in its print header.
     */
    document.title = ' '

    const restoreTitle = () => {
        document.title = originalTitle
        window.removeEventListener('afterprint', restoreTitle)
    }

    window.addEventListener('afterprint', restoreTitle)

    window.setTimeout(() => {
        window.print()

        /*
         * Fallback for browsers that do not reliably fire afterprint.
         */
        window.setTimeout(restoreTitle, 1000)
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

const formatPrintDate = (value, emptyText = '-') => {
    if (!value) {
        return emptyText
    }

    const rawValue = String(value).trim()
    const dateOnlyMatch = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})$/)

    let date

    if (dateOnlyMatch) {
        date = new Date(
            Number(dateOnlyMatch[1]),
            Number(dateOnlyMatch[2]) - 1,
            Number(dateOnlyMatch[3])
        )
    } else {
        date = new Date(rawValue.replace(' ', 'T'))
    }

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
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
            <div class="mx-auto max-w-screen-2xl px-4 py-4 sm:px-6 sm:py-5 lg:px-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="break-words text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            {{ pageTitle }}
                        </h1>

                        <p class="mt-2 text-sm text-slate-500">
                            Document Tracking System workspaces
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <div
                            v-if="activeSection !== 'reports'"
                            class="flex w-full items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 sm:w-auto"
                        >
                            <label class="shrink-0 text-sm font-bold text-blue-800">
                                Year:
                            </label>

                            <div class="min-w-0 flex-1 sm:min-w-[11rem] sm:flex-none">
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

        <main class="mx-auto max-w-screen-2xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
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

            <!-- AUTOMATIC 3-DAY STATUS REMINDER MODAL -->
            <div
                v-if="showAutomaticReminderModal"
                class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/70 px-0 py-0 backdrop-blur-sm sm:items-center sm:px-4 sm:py-8"
            >
                <div class="flex h-[100dvh] max-h-[100dvh] w-full max-w-4xl flex-col overflow-hidden rounded-none bg-white shadow-2xl sm:h-auto sm:max-h-[92vh] sm:rounded-[2rem]">
                    <div class="shrink-0 border-b border-red-200 bg-red-700 px-4 py-4 text-white sm:px-6 sm:py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.22em] text-red-100">
                                    NOTIFICATION REMINDER
                                </p>
                            </div>

                            <button
                                type="button"
                                class="w-full rounded-xl bg-white/15 px-4 py-3 text-sm font-black text-white hover:bg-white/25 sm:w-auto sm:py-2"
                                @click="closeAutomaticReminderModal"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                        <div class="grid grid-cols-1 gap-3 min-[430px]:grid-cols-3">
                       
                        </div>

                        <div class="mt-5 space-y-3 sm:max-h-[55vh] sm:overflow-y-auto sm:pr-1">
                            <article
                                v-for="doc in automaticReminderItems"
                                :key="`automatic-reminder-${doc.IDdoc}-${doc.current_status}`"
                                class="rounded-2xl border border-red-200 bg-red-50/70 p-4"
                            >
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-red-800 ring-1 ring-red-200">
                                                DTS - #{{ doc.document_no || doc.IDdoc }}
                                            </span>

                                            <span
                                                class="rounded-full border px-3 py-1 text-xs font-black"
                                                :class="automaticReminderBadgeClass(doc.current_status)"
                                            >
                                                {{ doc.current_status }}
                                            </span>

                                            <span class="rounded-full bg-red-600 px-3 py-1 text-xs font-black text-white">
                                                {{ formatPendingDays(doc.days_pending) }} day<span v-if="formatPendingDays(doc.days_pending) !== 1">s</span>
                                            </span>
                                        </div>

                                        <p class="mt-3 break-words text-base font-black text-slate-950">
                                            {{ doc.subject || 'No subject' }}
                                        </p>

                                        <div class="mt-3 grid grid-cols-1 gap-2 text-sm font-semibold text-slate-700 md:grid-cols-2">
                                            <p>
                                                <span class="font-black text-slate-900">Status Since:</span>
                                                {{ formatDateTime(doc.status_started_at) }}
                                            </p>

                                            <p>
                                                <span class="font-black text-slate-900">From:</span>
                                                {{ doc.from_office || '-' }}
                                            </p>

                                            <p>
                                                <span class="font-black text-slate-900">Current Office:</span>
                                                {{ doc.current_office || '-' }}
                                            </p>

                                            <p>
                                                <span class="font-black text-slate-900">Document Type:</span>
                                                {{ doc.code || doc.doctype || '-' }}
                                            </p>
                                        </div>
                                    </div>

                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="w-full shrink-0 rounded-xl bg-red-600 px-5 py-3 text-center text-sm font-black text-white hover:bg-red-700 lg:w-auto lg:py-2.5"
                                        @click="closeAutomaticReminderModal"
                                    >
                                        Review Document
                                    </Link>
                                </div>
                            </article>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Document Notification Popup -->
            <div
                v-if="showTransferNotificationModal"
                class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 px-0 py-0 backdrop-blur-sm sm:items-center sm:px-4 sm:py-8"
            >
                <div class="flex h-[100dvh] max-h-[100dvh] w-full max-w-3xl flex-col overflow-hidden rounded-none bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:rounded-[2rem]">
                    <div class="shrink-0 border-b border-blue-100 bg-blue-600 px-4 py-4 text-white sm:px-6 sm:py-5">
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
                                class="w-full rounded-xl bg-white/15 px-4 py-3 text-sm font-black text-white hover:bg-white/25 sm:w-auto sm:py-2"
                                @click="closeTransferNotificationModal"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
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
                                                Doc ID: {{ doc.document_no || doc.IDdoc }}
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
                <div class="flex gap-2 overflow-x-auto pb-1 sm:flex-wrap sm:overflow-visible sm:pb-0">
                    <Link
                        v-for="tab in pageTabs"
                        :key="tab.label"
                        :href="tab.href"
                        class="inline-flex min-h-[48px] shrink-0 items-center gap-3 rounded-2xl border px-5 py-3 text-sm font-bold transition-all"
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
                                     DTS Reports
                                </h2>
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
                                        Reports: By Date
                                    </h3>

                                    <p class="mt-1 text-sm font-medium text-black">
                                        Filter documents by classification, assigned user, and date range.
                                    </p>
                                </div>

                                <span class="inline-flex w-fit rounded-full bg-blue-700 px-4 py-1.5 text-xs font-bold text-white">
                                    Preview Report
                                </span>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-1 gap-5">
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-[180px_1fr] md:items-center">
                                    <label class="text-sm font-bold text-black md:text-right">
                                        Classification:
                                    </label>

                                    <select
                                        v-model="reportClassification"
                                        class="w-full rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        @change="loadReportAutomatically"
                                    >
                                        <option value="">
                                            All
                                        </option>

                                        <option value="False">
                                            Incoming
                                        </option>

                                        <option value="True">
                                            Outgoing
                                        </option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-1 gap-2 md:grid-cols-[180px_1fr] md:items-center">
                                    <label class="text-sm font-bold text-black md:text-right">
                                        User:
                                    </label>

                                    <select
                                        v-model="reportUser"
                                        class="w-full rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        @change="loadReportAutomatically"
                                    >
                                        <option value="">
                                            All Users
                                        </option>

                                        <option
                                            v-for="user in reportUserOptions"
                                            :key="`report-user-${reportUserOptionId(user)}`"
                                            :value="reportUserOptionId(user)"
                                        >
                                            {{ reportUserOptionLabel(user) }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                                    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5">
                                        <p class="mb-4 text-sm font-bold uppercase tracking-wide text-blue-700">
                                            Start Date
                                        </p>

                                        <input
                                            :value="reportStartDateDisplay"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            maxlength="10"
                                            placeholder="DD/MM/YYYY"
                                            class="w-full rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-bold text-black outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            @input="updateReportDate('start', $event.target.value)"
                                        />

                                        <p class="mt-2 text-xs font-semibold text-blue-700">
                                            Format: DD/MM/YYYY
                                        </p>
                                    </div>

                                    <div class="rounded-2xl border border-blue-100 bg-blue-50 p-5">
                                        <p class="mb-4 text-sm font-bold uppercase tracking-wide text-blue-700">
                                            End Date
                                        </p>

                                        <input
                                            :value="reportEndDateDisplay"
                                            type="text"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            maxlength="10"
                                            placeholder="DD/MM/YYYY"
                                            class="w-full rounded-xl border border-blue-200 bg-white px-4 py-3 text-sm font-bold text-black outline-none placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            @input="updateReportDate('end', $event.target.value)"
                                        />

                                        <p class="mt-2 text-xs font-semibold text-blue-700">
                                            Format: DD/MM/YYYY
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="reportErrors.start_date || reportErrors.end_date || reportErrors.date"
                                    class="rounded-xl border border-red-300 bg-red-50 px-5 py-4 text-sm font-bold text-red-800"
                                >
                                    <p v-if="reportErrors.start_date">
                                        {{ reportErrors.start_date }}
                                    </p>

                                    <p v-if="reportErrors.end_date">
                                        {{ reportErrors.end_date }}
                                    </p>

                                    <p v-if="reportErrors.date">
                                        {{ reportErrors.date }}
                                    </p>
                                </div>

                                <div class="flex justify-end border-t border-blue-100 pt-5">
                                    <button
                                        type="button"
                                        class="w-full rounded-xl bg-green-600 px-7 py-3 text-sm font-bold text-white shadow-sm hover:bg-green-700 sm:w-auto"
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
                    <div class="border-b border-blue-100 bg-blue-600 px-4 py-4 sm:px-6 sm:py-5">
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
                                        Monitoring Report
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
                                    User:
                                    <span class="font-bold">
                                        {{ selectedReportUserLabel }}
                                    </span>
                                </p>

                                <p v-if="reportStartDate || reportEndDate" class="mt-1">
                                    Date Range:
                                    <span class="screen-report-date-range font-bold">
                                        {{ reportStartDateDisplay || 'Start' }} to {{ reportEndDateDisplay || 'End' }}
                                    </span>

                                    <span class="print-report-date-range hidden font-bold">
                                        {{ reportStartDate ? formatPrintDate(reportStartDate) : 'Start' }}
                                        to
                                        {{ reportEndDate ? formatPrintDate(reportEndDate) : 'End' }}
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
                                        Doc ID
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
                                        {{ doc.document_no || doc.IDdoc }}
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
                                            Try another date range or filter.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Print-only table: requested print columns only -->
                        <table class="print-report-table w-full table-fixed border-collapse text-center text-xs">
                            <thead>
                                <tr class="print-page-top-spacer">
                                    <th colspan="6"></th>
                                </tr>

                                <tr class="bg-blue-50 text-black">
                                    <th class="w-[10%] border border-black px-3 py-3 font-bold">
                                        Doc ID
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
                                        {{ doc.document_no || doc.IDdoc }}
                                    </td>

                                    <td class="border border-black px-3 py-3 font-semibold text-black">
                                        {{ formatPrintDate(doc.entrydate) }}
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
                                            Try another date range or filter.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>

                            <tfoot>
                                <tr class="print-page-bottom-spacer">
                                    <td colspan="6"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            </div>

            <!-- RECEIVED DOCS CONTENT -->
            <div
                v-else-if="activeSection === 'received-docs'"
                class="rounded-2xl border border-blue-200 bg-white p-4 shadow-sm sm:p-6"
            >
                <div class="mb-5">
                    <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                        Incoming Documents
                    </h2>

                    <p class="mt-2 text-sm font-medium text-black">
                        View and filter incoming documents received by keeper and document type.
                    </p>
                </div>

                <div class="mb-8 rounded-xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
                        <div class="lg:col-span-4">
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search DOC ID, to, subject, regarding, status, or date sent..."
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

                                                <!-- Responsive Incoming Documents list -->
                <div class="space-y-4 xl:hidden">
                    <article
                        v-for="doc in rows"
                        :key="`incoming-card-${doc.IDdoc}`"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-blue-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">
                                    DOC ID
                                </p>

                                <Link
                                    :href="`/dts/${doc.IDdoc}`"
                                    class="mt-1 inline-flex break-all text-base font-black text-blue-700 hover:underline"
                                >
                                    DTS - #{{ doc.document_no || doc.IDdoc }}
                                </Link>
                            </div>

                            <div>
                                <p class="mb-1 text-[11px] font-black uppercase tracking-wide text-slate-400 sm:text-right">
                                    Status
                                </p>

                                <span
                                    class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-black"
                                    :class="documentStatusClass(doc)"
                                >
                                    {{ documentStatusLabel(doc) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    To
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-900">
                                    {{ documentToDisplay(doc) }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Subject
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-base font-black leading-6 text-slate-950">
                                    {{ doc.subject || 'No subject' }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Regarding
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-800">
                                    {{ doc.regarding || '-' }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Date Sent
                                </p>

                                <p class="mt-1 break-words text-sm font-bold text-slate-900">
                                    {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                </p>
                            </div>

                            <div
                                v-if="shouldShowReturnedBy(doc)"
                                class="sm:col-span-2"
                            >
                                <p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black text-rose-700">
                                    Returned By: {{ returnedByDisplay(doc) }}
                                </p>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 bg-slate-50 p-4">
                            <button
                                v-if="canShowReceiveButton(doc)"
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-black text-white hover:bg-blue-700"
                                @click="receiveTransferredDocument(doc)"
                            >
                                Receive Document
                            </button>

                            <Link
                                v-else
                                :href="`/dts/${doc.IDdoc}`"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-blue-300 bg-white px-4 py-3 text-sm font-black text-blue-700 hover:bg-blue-50"
                            >
                                View Details
                            </Link>
                        </div>
                    </article>

                    <div
                        v-if="rows.length === 0"
                        class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center"
                    >
                        <div class="text-lg font-bold text-black">
                            No incoming documents found
                        </div>

                        <p class="mt-2 text-sm font-medium text-black">
                            Try another keeper, date, or reset the filters.
                        </p>
                    </div>
                </div>

                <!-- Desktop table layout -->
                <div class="hidden overflow-hidden rounded-xl border border-black xl:block">
                    <table class="w-full table-fixed border-collapse text-center text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="w-[10%] border border-black px-2 py-3 text-center font-bold">
                                    DOC ID
                                </th>

                                <th class="w-[15%] border border-black px-2 py-3 text-center font-bold">
                                    To
                                </th>

                                <th class="w-[22%] border border-black px-2 py-3 text-center font-bold">
                                    Subject
                                </th>

                                <th class="w-[24%] border border-black px-2 py-3 text-center font-bold">
                                    Regarding
                                </th>

                                <th class="w-[10%] border border-black px-2 py-3 text-center font-bold">
                                    Status
                                </th>

                                <th class="w-[11%] border border-black px-2 py-3 text-center font-bold">
                                    Date Sent
                                </th>

                                <th class="w-[8%] border border-black px-2 py-3 text-center font-bold">
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
                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="font-bold text-blue-700 hover:underline"
                                    >
                                        {{ doc.document_no || doc.IDdoc }}
                                    </Link>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold leading-6 text-black">
                                        {{ documentToDisplay(doc) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                        {{ doc.subject || 'No subject' }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold leading-6 text-black">
                                        {{ doc.regarding || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                        :class="documentStatusClass(doc)"
                                    >
                                        {{ documentStatusLabel(doc) }}
                                    </span>

                                    <p
                                        v-if="shouldShowReturnedBy(doc)"
                                        class="mt-2 text-[11px] font-black leading-4 text-rose-700"
                                    >
                                        Returned By: {{ returnedByDisplay(doc) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <button
                                        v-if="canShowReceiveButton(doc)"
                                        type="button"
                                        class="inline-flex w-full max-w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                        @click="receiveTransferredDocument(doc)"
                                    >
                                        Receive
                                    </button>

                                    <Link
                                        v-else
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="inline-flex w-full max-w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="border border-black px-7 py-14 text-center">
                                    <div class="text-lg font-bold text-black">
                                        No incoming documents found
                                    </div>

                                    <p class="mt-2 text-sm font-medium text-black">
                                        Try another keeper, date, or reset the filters.
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
                                placeholder="Search Doc ID, type, office, or subject..."
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
                                    Doc<br>ID
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
                                {{ doc.document_no || doc.IDdoc }}
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
                                placeholder="Search Doc ID, type, office, or subject..."
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
                                Doc ID
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
                                    {{ doc.document_no || doc.IDdoc }}
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
                                placeholder="Search Doc ID, type, office, or subject..."
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
                                    Doc ID
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
                                        {{ doc.document_no || doc.IDdoc }}
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
                class="rounded-2xl border border-blue-200 bg-white p-4 shadow-sm sm:p-6"
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

                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto] lg:items-end">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search DOC ID, to, subject, regarding, status, or date sent..."
                                class="w-full rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-black outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                @keyup.enter="runSearch"
                            />
                        </div>

                        <button
                            type="button"
                            class="w-full rounded-lg border border-blue-400 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100 lg:w-auto"
                            @click="runSearch"
                        >
                            Search
                        </button>

                        <button
                            type="button"
                            class="w-full rounded-lg border border-blue-300 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 hover:bg-blue-100 lg:w-auto"
                            @click="resetSearch"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                                                <!-- Responsive Incoming Documents list -->
                <div class="space-y-4 xl:hidden">
                    <article
                        v-for="doc in rows"
                        :key="`incoming-card-${doc.IDdoc}`"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-blue-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">
                                    DOC ID
                                </p>

                                <Link
                                    :href="`/dts/${doc.IDdoc}`"
                                    class="mt-1 inline-flex break-all text-base font-black text-blue-700 hover:underline"
                                >
                                    DTS - #{{ doc.document_no || doc.IDdoc }}
                                </Link>
                            </div>

                            <div>
                                <p class="mb-1 text-[11px] font-black uppercase tracking-wide text-slate-400 sm:text-right">
                                    Status
                                </p>

                                <span
                                    class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-black"
                                    :class="documentStatusClass(doc)"
                                >
                                    {{ documentStatusLabel(doc) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    To
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-900">
                                    {{ documentToDisplay(doc) }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Subject
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-base font-black leading-6 text-slate-950">
                                    {{ doc.subject || 'No subject' }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Regarding
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-800">
                                    {{ doc.regarding || '-' }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Date Sent
                                </p>

                                <p class="mt-1 break-words text-sm font-bold text-slate-900">
                                    {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                </p>
                            </div>

                            <div
                                v-if="shouldShowReturnedBy(doc)"
                                class="sm:col-span-2"
                            >
                                <p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black text-rose-700">
                                    Returned By: {{ returnedByDisplay(doc) }}
                                </p>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 bg-slate-50 p-4">
                            <button
                                v-if="canShowReceiveButton(doc)"
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-black text-white hover:bg-blue-700"
                                @click="receiveTransferredDocument(doc)"
                            >
                                Receive Document
                            </button>

                            <Link
                                v-else
                                :href="`/dts/${doc.IDdoc}`"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-blue-300 bg-white px-4 py-3 text-sm font-black text-blue-700 hover:bg-blue-50"
                            >
                                View Details
                            </Link>
                        </div>
                    </article>

                    <div
                        v-if="rows.length === 0"
                        class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center"
                    >
                        <div class="text-lg font-bold text-black">
                            {{ activeSection === 'incoming' && !activeFilter ? 'No incoming documents found' : 'No documents for receiving found' }}
                        </div>

                        <p class="mt-2 text-sm font-medium text-black">
                            No records available.
                        </p>
                    </div>
                </div>

                <!-- Desktop table layout -->
                <div class="hidden overflow-hidden rounded-xl border border-black xl:block">
                    <table class="w-full table-fixed border-collapse text-center text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="w-[10%] border border-black px-2 py-3 text-center font-bold">
                                    DOC ID
                                </th>

                                <th class="w-[15%] border border-black px-2 py-3 text-center font-bold">
                                    To
                                </th>

                                <th class="w-[22%] border border-black px-2 py-3 text-center font-bold">
                                    Subject
                                </th>

                                <th class="w-[24%] border border-black px-2 py-3 text-center font-bold">
                                    Regarding
                                </th>

                                <th class="w-[10%] border border-black px-2 py-3 text-center font-bold">
                                    Status
                                </th>

                                <th class="w-[11%] border border-black px-2 py-3 text-center font-bold">
                                    Date Sent
                                </th>

                                <th class="w-[8%] border border-black px-2 py-3 text-center font-bold">
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
                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="font-bold text-blue-700 hover:underline"
                                    >
                                        {{ doc.document_no || doc.IDdoc }}
                                    </Link>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold leading-6 text-black">
                                        {{ documentToDisplay(doc) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                        {{ doc.subject || 'No subject' }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold leading-6 text-black">
                                        {{ doc.regarding || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                        :class="documentStatusClass(doc)"
                                    >
                                        {{ documentStatusLabel(doc) }}
                                    </span>

                                    <p
                                        v-if="shouldShowReturnedBy(doc)"
                                        class="mt-2 text-[11px] font-black leading-4 text-rose-700"
                                    >
                                        Returned By: {{ returnedByDisplay(doc) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <button
                                        v-if="canShowReceiveButton(doc)"
                                        type="button"
                                        class="inline-flex w-full max-w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                        @click="receiveTransferredDocument(doc)"
                                    >
                                        Receive
                                    </button>

                                    <Link
                                        v-else
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="inline-flex w-full max-w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="border border-black px-7 py-14 text-center">
                                    <div class="text-lg font-bold text-black">
                                        {{ activeSection === 'incoming' && !activeFilter ? 'No incoming documents found' : 'No documents for receiving found' }}
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
                v-else-if="['collab-received', 'received', 'for-action'].includes(activeFilter)"
                class="rounded-2xl border border-blue-200 bg-white p-4 shadow-sm sm:p-6"
            >
                <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                            Received
                        </h2>

                        <p class="mt-2 text-sm font-medium text-black">
                            Received documents that do not have a saved action yet.
                        </p>
                    </div>

                    <div class="text-sm font-bold text-black">
                        Page {{ currentPage }} of {{ lastPage }}
                    </div>
                </div>

                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto] lg:items-end">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search DOC ID, to, subject, regarding, status, or date sent..."
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

                                                <!-- Responsive Incoming Documents list -->
                <div class="space-y-4 xl:hidden">
                    <article
                        v-for="doc in rows"
                        :key="`incoming-card-${doc.IDdoc}`"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-blue-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">
                                    DOC ID
                                </p>

                                <Link
                                    :href="`/dts/${doc.IDdoc}`"
                                    class="mt-1 inline-flex break-all text-base font-black text-blue-700 hover:underline"
                                >
                                    DTS - #{{ doc.document_no || doc.IDdoc }}
                                </Link>
                            </div>

                            <div>
                                <p class="mb-1 text-[11px] font-black uppercase tracking-wide text-slate-400 sm:text-right">
                                    Status
                                </p>

                                <span
                                    class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-black"
                                    :class="documentStatusClass(doc)"
                                >
                                    {{ documentStatusLabel(doc) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    To
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-900">
                                    {{ documentToDisplay(doc) }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Subject
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-base font-black leading-6 text-slate-950">
                                    {{ doc.subject || 'No subject' }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Regarding
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-800">
                                    {{ doc.regarding || '-' }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Date Sent
                                </p>

                                <p class="mt-1 break-words text-sm font-bold text-slate-900">
                                    {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                </p>
                            </div>

                            <div
                                v-if="shouldShowReturnedBy(doc)"
                                class="sm:col-span-2"
                            >
                                <p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black text-rose-700">
                                    Returned By: {{ returnedByDisplay(doc) }}
                                </p>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 bg-slate-50 p-4">
                            <button
                                v-if="canShowReceiveButton(doc)"
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-black text-white hover:bg-blue-700"
                                @click="receiveTransferredDocument(doc)"
                            >
                                Receive Document
                            </button>

                            <Link
                                v-else
                                :href="`/dts/${doc.IDdoc}`"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-blue-300 bg-white px-4 py-3 text-sm font-black text-blue-700 hover:bg-blue-50"
                            >
                                View Details
                            </Link>
                        </div>
                    </article>

                    <div
                        v-if="rows.length === 0"
                        class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center"
                    >
                        <div class="text-lg font-bold text-black">
                            No received documents found
                        </div>

                        <p class="mt-2 text-sm font-medium text-black">
                            No records available.
                        </p>
                    </div>
                </div>

                <!-- Desktop table layout -->
                <div class="hidden overflow-hidden rounded-xl border border-black xl:block">
                    <table class="w-full table-fixed border-collapse text-center text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="w-[10%] border border-black px-2 py-3 text-center font-bold">
                                    DOC ID
                                </th>

                                <th class="w-[15%] border border-black px-2 py-3 text-center font-bold">
                                    To
                                </th>

                                <th class="w-[22%] border border-black px-2 py-3 text-center font-bold">
                                    Subject
                                </th>

                                <th class="w-[24%] border border-black px-2 py-3 text-center font-bold">
                                    Regarding
                                </th>

                                <th class="w-[10%] border border-black px-2 py-3 text-center font-bold">
                                    Status
                                </th>

                                <th class="w-[11%] border border-black px-2 py-3 text-center font-bold">
                                    Date Sent
                                </th>

                                <th class="w-[8%] border border-black px-2 py-3 text-center font-bold">
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
                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="font-bold text-blue-700 hover:underline"
                                    >
                                        {{ doc.document_no || doc.IDdoc }}
                                    </Link>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold leading-6 text-black">
                                        {{ documentToDisplay(doc) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                        {{ doc.subject || 'No subject' }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold leading-6 text-black">
                                        {{ doc.regarding || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                        :class="documentStatusClass(doc)"
                                    >
                                        {{ documentStatusLabel(doc) }}
                                    </span>

                                    <p
                                        v-if="shouldShowReturnedBy(doc)"
                                        class="mt-2 text-[11px] font-black leading-4 text-rose-700"
                                    >
                                        Returned By: {{ returnedByDisplay(doc) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <button
                                        v-if="canShowReceiveButton(doc)"
                                        type="button"
                                        class="inline-flex w-full max-w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                        @click="receiveTransferredDocument(doc)"
                                    >
                                        Receive
                                    </button>

                                    <Link
                                        v-else
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="inline-flex w-full max-w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="border border-black px-7 py-14 text-center">
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

            <!-- IN PROGRESS / COMPLETED / RETURNED CONTENT -->
                <div
                    v-else-if="['in-progress', 'addressed', 'completed', 'returned'].includes(activeFilter)"
                    class="rounded-2xl border border-blue-200 bg-white p-4 shadow-sm sm:p-6"
                >
                    <div class="mb-5 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="text-2xl font-bold tracking-wide text-cyan-700">
                                {{ ['in-progress', 'addressed', 'completed'].includes(activeFilter)
                                    ? 'Addressed'
                                    : 'Returned'
                                }}
                            </h2>

                            <p class="mt-2 text-sm font-medium text-black">
                                {{ ['in-progress', 'addressed', 'completed'].includes(activeFilter)
                                    ? 'Documents handled using the Address action.'
                                    : 'List of returned documents.'
                                }}
                            </p>
                        </div>

                        <div class="text-sm font-bold text-black">
                            Page {{ currentPage }} of {{ lastPage }}
                        </div>
                    </div>

                <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto] lg:items-end">
                        <div>
                            <label class="mb-2 block text-sm font-bold text-black">
                                Search:
                            </label>

                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search DOC ID, to, subject, regarding, status, or date sent..."
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

                                                    <!-- Responsive Incoming Documents list -->
                <div class="space-y-4 xl:hidden">
                    <article
                        v-for="doc in rows"
                        :key="`incoming-card-${doc.IDdoc}`"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-blue-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-blue-600">
                                    DOC ID
                                </p>

                                <Link
                                    :href="`/dts/${doc.IDdoc}`"
                                    class="mt-1 inline-flex break-all text-base font-black text-blue-700 hover:underline"
                                >
                                    DTS - #{{ doc.document_no || doc.IDdoc }}
                                </Link>
                            </div>

                            <div>
                                <p class="mb-1 text-[11px] font-black uppercase tracking-wide text-slate-400 sm:text-right">
                                    Status
                                </p>

                                <span
                                    class="inline-flex w-fit rounded-full border px-3 py-1 text-xs font-black"
                                    :class="documentStatusClass(doc)"
                                >
                                    {{ documentStatusLabel(doc) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    To
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-900">
                                    {{ documentToDisplay(doc) }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Subject
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-base font-black leading-6 text-slate-950">
                                    {{ doc.subject || 'No subject' }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Regarding
                                </p>

                                <p class="mt-1 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-800">
                                    {{ doc.regarding || '-' }}
                                </p>
                            </div>

                            <div class="sm:col-span-2">
                                <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                    Date Sent
                                </p>

                                <p class="mt-1 break-words text-sm font-bold text-slate-900">
                                    {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                </p>
                            </div>

                            <div
                                v-if="shouldShowReturnedBy(doc)"
                                class="sm:col-span-2"
                            >
                                <p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black text-rose-700">
                                    Returned By: {{ returnedByDisplay(doc) }}
                                </p>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 bg-slate-50 p-4">
                            <button
                                v-if="canShowReceiveButton(doc)"
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-black text-white hover:bg-blue-700"
                                @click="receiveTransferredDocument(doc)"
                            >
                                Receive Document
                            </button>

                            <Link
                                v-else
                                :href="`/dts/${doc.IDdoc}`"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-blue-300 bg-white px-4 py-3 text-sm font-black text-blue-700 hover:bg-blue-50"
                            >
                                View Details
                            </Link>
                        </div>
                    </article>

                    <div
                        v-if="rows.length === 0"
                        class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-12 text-center"
                    >
                        <div class="text-lg font-bold text-black">
                            {{ ['in-progress', 'addressed', 'completed'].includes(activeFilter) ? 'No addressed documents found' : 'No returned documents found' }}
                        </div>

                        <p class="mt-2 text-sm font-medium text-black">
                            No records available.
                        </p>
                    </div>
                </div>

                <!-- Desktop table layout -->
                <div class="hidden overflow-hidden rounded-xl border border-black xl:block">
                    <table class="w-full table-fixed border-collapse text-center text-sm">
                        <thead>
                            <tr class="bg-blue-600 text-white">
                                <th class="w-[10%] border border-black px-2 py-3 text-center font-bold">
                                    DOC ID
                                </th>

                                <th class="w-[15%] border border-black px-2 py-3 text-center font-bold">
                                    To
                                </th>

                                <th class="w-[22%] border border-black px-2 py-3 text-center font-bold">
                                    Subject
                                </th>

                                <th class="w-[24%] border border-black px-2 py-3 text-center font-bold">
                                    Regarding
                                </th>

                                <th class="w-[10%] border border-black px-2 py-3 text-center font-bold">
                                    Status
                                </th>

                                <th class="w-[11%] border border-black px-2 py-3 text-center font-bold">
                                    Date Sent
                                </th>

                                <th class="w-[8%] border border-black px-2 py-3 text-center font-bold">
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
                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <Link
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="font-bold text-blue-700 hover:underline"
                                    >
                                        {{ doc.document_no || doc.IDdoc }}
                                    </Link>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold leading-6 text-black">
                                        {{ documentToDisplay(doc) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words text-base font-bold leading-6 text-black">
                                        {{ doc.subject || 'No subject' }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="whitespace-pre-line break-words font-semibold leading-6 text-black">
                                        {{ doc.regarding || '-' }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                        :class="documentStatusClass(doc)"
                                    >
                                        {{ documentStatusLabel(doc) }}
                                    </span>

                                    <p
                                        v-if="shouldShowReturnedBy(doc)"
                                        class="mt-2 text-[11px] font-black leading-4 text-rose-700"
                                    >
                                        Returned By: {{ returnedByDisplay(doc) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <p class="font-bold text-black">
                                        {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                    </p>
                                </td>

                                <td class="border border-black px-2 py-3 align-middle text-center">
                                    <button
                                        v-if="canShowReceiveButton(doc)"
                                        type="button"
                                        class="inline-flex w-full max-w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                        @click="receiveTransferredDocument(doc)"
                                    >
                                        Receive
                                    </button>

                                    <Link
                                        v-else
                                        :href="`/dts/${doc.IDdoc}`"
                                        class="inline-flex w-full max-w-20 justify-center rounded-lg border border-blue-700 bg-white px-3 py-2 text-xs font-bold text-blue-700 hover:bg-blue-50"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>

                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="border border-black px-7 py-14 text-center">
                                    <div class="text-lg font-bold text-black">
                                        {{ ['in-progress', 'addressed', 'completed'].includes(activeFilter) ? 'No addressed documents found' : 'No returned documents found' }}
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
                    class="mb-6 grid grid-cols-[repeat(auto-fit,minmax(145px,1fr))] gap-3 lg:mb-8 xl:gap-4"
                >
                    <Link
                        :href="buildDtsUrl({ section: userRights === '2' ? 'all-documents' : 'documents' })"
                        class="group relative min-h-[118px] overflow-hidden rounded-[1.35rem] xl:min-h-[132px] xl:rounded-[1.6rem] bg-gradient-to-br from-blue-600 to-indigo-600 p-3 text-white shadow-xl xl:p-4 2xl:p-5 shadow-blue-100 transition hover:-translate-y-1 hover:shadow-2xl"
                    >
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-3 2xl:gap-4">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15 text-lg backdrop-blur xl:h-10 xl:w-10 xl:text-xl 2xl:h-12 2xl:w-12 2xl:rounded-2xl 2xl:text-2xl">
                                📄
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-xs font-black leading-tight text-white/90 xl:text-sm 2xl:text-base">
                                        Total Documents
                                    </p>

                                    
                                </div>

                                <p class="mt-2 text-3xl font-black leading-none tracking-tight xl:text-4xl 2xl:mt-3 2xl:text-5xl">
                                    {{ props.stats.total }}
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="buildDtsUrl({ section: 'incoming', filter: 'for-receiving' })"
                        class="group relative min-h-[118px] overflow-hidden rounded-[1.35rem] xl:min-h-[132px] xl:rounded-[1.6rem] bg-gradient-to-br from-violet-600 to-fuchsia-600 p-3 text-white shadow-xl xl:p-4 2xl:p-5 shadow-violet-100 transition hover:-translate-y-1 hover:shadow-2xl"
                    >
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-3 2xl:gap-4">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15 text-lg backdrop-blur xl:h-10 xl:w-10 xl:text-xl 2xl:h-12 2xl:w-12 2xl:rounded-2xl 2xl:text-2xl">
                                ⏳
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-xs font-black leading-tight text-white/90 xl:text-sm 2xl:text-base">
                                        For Receiving
                                    </p>
                                </div>

                                <p class="mt-2 text-3xl font-black leading-none tracking-tight xl:text-4xl 2xl:mt-3 2xl:text-5xl">
                                    {{ props.stats.for_receiving }}
                                </p>

                                <p class="mt-2 hidden text-xs font-semibold leading-5 text-white/75 2xl:block">
                                    Click to view pending receiving
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="buildDtsUrl({ section: 'incoming', filter: 'received' })"
                        class="group relative min-h-[118px] overflow-hidden rounded-[1.35rem] xl:min-h-[132px] xl:rounded-[1.6rem] bg-gradient-to-br from-emerald-600 to-green-500 p-3 text-white shadow-xl xl:p-4 2xl:p-5 shadow-emerald-100 transition hover:-translate-y-1 hover:shadow-2xl">
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-3 2xl:gap-4">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15 text-lg backdrop-blur xl:h-10 xl:w-10 xl:text-xl 2xl:h-12 2xl:w-12 2xl:rounded-2xl 2xl:text-2xl">
                                ✅
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-xs font-black leading-tight text-white/90 xl:text-sm 2xl:text-base">
                                        Received
                                    </p>
                                </div>

                                <p class="mt-2 text-3xl font-black leading-none tracking-tight xl:text-4xl 2xl:mt-3 2xl:text-5xl">
                                    {{ props.stats.received }}
                                </p>

                                <p class="mt-2 hidden text-xs font-semibold leading-5 text-white/75 2xl:block">
                                    Received with no action yet
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        :href="buildDtsUrl({ section: 'incoming', filter: 'addressed' })"
                        class="group relative min-h-[118px] overflow-hidden rounded-[1.35rem] xl:min-h-[132px] xl:rounded-[1.6rem] bg-gradient-to-br from-cyan-600 to-sky-500 p-3 text-white shadow-xl xl:p-4 2xl:p-5 shadow-cyan-100 transition hover:-translate-y-1 hover:shadow-2xl"
                    >
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-3 2xl:gap-4">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15 text-lg backdrop-blur xl:h-10 xl:w-10 xl:text-xl 2xl:h-12 2xl:w-12 2xl:rounded-2xl 2xl:text-2xl">
                                📌
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-black leading-tight text-white/90 xl:text-sm 2xl:text-base">Addressed</p>
                                <p class="mt-2 text-3xl font-black leading-none tracking-tight xl:text-4xl 2xl:mt-3 2xl:text-5xl">
                                    {{ props.stats.addressed ?? props.stats.in_progress ?? 0 }}
                                </p>
                                <p class="mt-2 hidden text-xs font-semibold leading-5 text-white/75 2xl:block">
                                    Documents handled through Address
                                </p>
                            </div>
                        </div>
                    </Link>

                    <Link
                        v-if="canShowReturnedCard"
                        :href="buildDtsUrl({ section: 'incoming', filter: 'returned' })"
                        class="group relative min-h-[118px] overflow-hidden rounded-[1.35rem] xl:min-h-[132px] xl:rounded-[1.6rem] bg-gradient-to-br from-rose-600 to-red-500 p-3 text-white shadow-xl xl:p-4 2xl:p-5 shadow-rose-100 transition hover:-translate-y-1 hover:shadow-2xl"
                    >
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full bg-white/10"></div>

                        <div class="relative flex h-full items-start gap-3 2xl:gap-4">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/15 text-lg backdrop-blur xl:h-10 xl:w-10 xl:text-xl 2xl:h-12 2xl:w-12 2xl:rounded-2xl 2xl:text-2xl">
                                ↩️
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-black leading-tight text-white/90 xl:text-sm 2xl:text-base">Returned</p>
                                <p class="mt-2 text-3xl font-black leading-none tracking-tight xl:text-4xl 2xl:mt-3 2xl:text-5xl">
                                    {{ props.stats.returned ?? 0 }}
                                </p>
                                <p class="mt-2 hidden text-xs font-semibold leading-5 text-white/75 2xl:block">
                                    Documents returned 
                                </p>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Search -->
                <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:mb-8 sm:p-7">
                    <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">
                                Search Documents
                            </h2>

                            <p class="mt-1 text-sm text-slate-500">
                                Search by Document ID, subject, or regarding.
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
                            placeholder="Enter Document ID, subject, or regarding..."
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
                    <div class="rounded-t-2xl border-b border-blue-700 bg-blue-600 px-4 py-4 sm:px-7 sm:py-5">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-white">
                                    Document List
                                </h2>

                                <p class="mt-1 text-sm text-white">
                                    Latest document records from the DTS database.
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

                                <th class="w-[12%] border-b border-slate-200 px-4 py-4 text-center font-bold">
                                    STATUS
                                </th>

                                <th class="w-[16%] border-b border-slate-200 px-4 py-4 font-bold">
                                    DATE SENT
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
                                        {{ doc.document_no || doc.tracking_no || doc.IDdoc }}
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

                                <td class="px-4 py-5 text-center align-top">
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-xs font-black"
                                        :class="documentStatusClass(doc)"
                                    >
                                        {{ documentStatusLabel(doc) }}
                                    </span>

                                    <p
                                        v-if="shouldShowReturnedBy(doc)"
                                        class="mt-2 text-[11px] font-black leading-4 text-rose-700"
                                    >
                                        Returned By: {{ returnedByDisplay(doc) }}
                                    </p>
                                </td>

                                <td class="px-4 py-5 align-top text-slate-700">
                                    <div class="whitespace-normal break-words text-sm font-semibold leading-6">
                                        {{ formatDateTime(doc.date_sent || doc.distribution_date || doc.distdate || doc.entrydate) }}
                                    </div>
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
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 px-0 py-0 sm:items-center sm:px-4 sm:py-8"
        >
            <div class="max-h-[100dvh] w-full max-w-md overflow-y-auto rounded-t-[2rem] bg-white shadow-2xl sm:max-h-[90vh] sm:rounded-2xl">
                <div class="border-b border-blue-100 bg-blue-600 px-6 py-5">
                    <h2 class="text-xl font-bold text-white">
                        Confirm Action
                    </h2>

                    <p class="mt-1 text-sm font-medium text-blue-50">
                        Please confirm before continuing.
                    </p>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                        <p class="text-sm font-bold text-blue-700">
                            Document ID
                        </p>

                        <p class="mt-1 text-lg font-bold text-black">
                            {{ selectedPendingDocument?.document_no || selectedPendingDocument?.IDdoc }}
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

                <div class="flex flex-col-reverse gap-3 border-t border-blue-100 bg-blue-50 px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
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
            :next-document-id="props.nextDocumentId"
            @close="closeAddDocumentModal"
        />

        <!-- Edit Entry Date Modal -->
        <div
            v-if="canManageDts && showEditEntryDateModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 px-0 py-0 sm:items-center sm:px-4 sm:py-8"
        >
            <div class="max-h-[100dvh] w-full max-w-md overflow-y-auto rounded-t-[2rem] bg-white shadow-2xl sm:rounded-2xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-4 sm:px-6 sm:py-5">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">
                            Edit Entry Date
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Doc ID: {{ selectedDocument?.IDdoc }}
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

                <form class="space-y-5 p-4 sm:p-6" @submit.prevent="submitEntryDateUpdate">
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

                    <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
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

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
    }

    .report-print-area {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        box-sizing: border-box !important;
        width: 100% !important;
        padding: 12mm !important;
        border: none !important;
        box-shadow: none !important;
    }

    .no-print {
        display: none !important;
    }

    .screen-report-table {
        display: none !important;
    }

    .screen-report-date-range {
        display: none !important;
    }

    .print-report-date-range {
        display: inline !important;
    }

    .print-report-table {
        display: table !important;
        width: 100% !important;
        min-width: 0 !important;
        font-size: 10px !important;
    }

    .print-report-table thead {
        display: table-header-group !important;
    }

    .print-report-table tfoot {
        display: table-footer-group !important;
    }

    .print-report-table tbody tr {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    .print-report-table th,
    .print-report-table td {
        padding: 6px !important;
        vertical-align: top !important;
    }

    .print-report-table .print-page-top-spacer th {
        height: 10mm !important;
        padding: 0 !important;
        border: 0 !important;
        background: white !important;
    }

    .print-report-table .print-page-bottom-spacer td {
        height: 7mm !important;
        padding: 0 !important;
        border: 0 !important;
        background: white !important;
    }

    
    @page {
        size: landscape;
        margin: 0;
    }
}
@media (max-width: 639px) {
    html,
    body {
        max-width: 100%;
        overflow-x: hidden;
    }

    input,
    select,
    textarea {
        font-size: 16px !important;
    }

    button,
    a {
        -webkit-tap-highlight-color: transparent;
    }

    .overflow-x-auto {
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .screen-report-table {
        min-width: 760px !important;
    }

    table th,
    table td {
        white-space: normal;
    }

    .report-print-area {
        overflow: hidden;
    }
}

</style>
