<script setup>

import { Link, router, usePage } from '@inertiajs/vue3'

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'



const formatNotificationDate = (value) => {

    if (!value) {

        return ''

    }

    const normalized = String(value).replace(' ', 'T')

    const parsedDate = new Date(normalized)

    if (Number.isNaN(parsedDate.getTime())) {

        return value

    }

    return new Intl.DateTimeFormat('en-US', {

        month: 'long',

        day: 'numeric',

        year: 'numeric',

        hour: 'numeric',

        minute: '2-digit',

        hour12: true,

    }).format(parsedDate)

}

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

    notificationCount: {

        type: Number,

        default: 0,

    },

})

const page = usePage()

const showUserMenu = ref(false)

const showNotifications = ref(false)

const showMobileSidebar = ref(false)

const seenNotificationKeys = ref([])

const activeAnnouncements = ref([])

const announcementLoading = ref(false)

let announcementRefreshTimer = null

const openMobileSidebar = () => {

    showMobileSidebar.value = true

    showUserMenu.value = false

}

const closeMobileSidebar = () => {

    showMobileSidebar.value = false

}

const authUser = computed(() => page.props.auth?.user || {})

const userDisplayName = computed(() => {

    return authUser.value?.name

        || authUser.value?.loginname

        || authUser.value?.username

        || 'User'

})

const userInitial = computed(() => {

    return String(userDisplayName.value || 'U').slice(0, 1).toUpperCase()

})

const userRights = computed(() => {

    return String(authUser.value?.rights ?? authUser.value?.role_id ?? '').trim()

})

const notificationStorageKey = computed(() => {

    const userId = page.props.auth?.user?.ID || page.props.auth?.user?.id || 'guest'

    return `dts_seen_notifications_${userId}`

})

const notificationKey = (item) => {

    if (item.notification_type === 'announcement') {

        return `announcement:${item.id || ''}`

    }

    return [

        item.notification_type || 'for_receiving',

        item.IDdoc || item.document_no || '',

        item.received_date || item.transfer_date || item.due_date || '',

    ].join(':')

}

const loadSeenNotificationKeys = () => {

    if (typeof window === 'undefined') {

        return

    }

    try {

        const stored = window.localStorage.getItem(notificationStorageKey.value)

        seenNotificationKeys.value = stored ? JSON.parse(stored) : []

    } catch (error) {

        seenNotificationKeys.value = []

    }

}

const saveSeenNotificationKeys = () => {

    if (typeof window === 'undefined') {

        return

    }

    window.localStorage.setItem(

        notificationStorageKey.value,

        JSON.stringify(seenNotificationKeys.value)

    )

}

const markNotificationSeen = (item) => {

    const key = notificationKey(item)

    if (!key || seenNotificationKeys.value.includes(key)) {

        return

    }

    seenNotificationKeys.value = [

        ...seenNotificationKeys.value,

        key,

    ]

    saveSeenNotificationKeys()

}

const isNotificationSeen = (item) => {

    return seenNotificationKeys.value.includes(notificationKey(item))

}

const fetchActiveAnnouncements = async () => {

    if (announcementLoading.value) {

        return

    }

    announcementLoading.value = true

    try {

        const response = await fetch('/dts/announcements/active', {

            method: 'GET',

            credentials: 'same-origin',

            headers: {

                Accept: 'application/json',

                'X-Requested-With': 'XMLHttpRequest',

            },

        })

        if (!response.ok) {

            throw new Error(`Announcement request failed (${response.status}).`)

        }

        const payload = await response.json()

        activeAnnouncements.value = Array.isArray(payload?.announcements)

            ? payload.announcements

            : []

    } catch (error) {

        console.error('Unable to load DTS announcements:', error)

        activeAnnouncements.value = []

    } finally {

        announcementLoading.value = false

    }

}

onMounted(() => {

    loadSeenNotificationKeys()

    fetchActiveAnnouncements()

    announcementRefreshTimer = window.setInterval(() => {

        fetchActiveAnnouncements()

    }, 60000)

})

watch(showMobileSidebar, (isOpen) => {

    if (typeof document === 'undefined') return

    document.body.style.overflow = isOpen ? 'hidden' : ''

})

onBeforeUnmount(() => {

    if (announcementRefreshTimer) {

        window.clearInterval(announcementRefreshTimer)

        announcementRefreshTimer = null

    }

    if (typeof document !== 'undefined') {

        document.body.style.overflow = ''

    }

})

const notificationItems = computed(() => {

    const viewerNotifications = page.props.viewerNotifications || []

    const creatorReceivedNotifications = page.props.creatorReceivedNotifications || []

    const notifications = page.props.notifications || []

    const announcements = activeAnnouncements.value.map((announcement) => ({

        ...announcement,

        notification_type: 'announcement',

    }))

    return [

        ...announcements,

        ...viewerNotifications,

        ...creatorReceivedNotifications,

        ...notifications,

    ]

})

const visibleNotificationItems = computed(() => {

    return notificationItems.value.filter((item) => {

        return !isNotificationSeen(item)

    })

})

const notificationReceiverName = (item) => {

    return item.received_by_name

        || item.received_by

        || item.receiver_name

        || item.confirmed_by_name

        || item.confirmuser_name

        || (item.confirmuser ? `Account #${item.confirmuser}` : 'Someone')

}

const notificationSubject = (item) => {

    if (item.notification_type === 'announcement') {

        return item.title || 'DTS Announcement'

    }

    return item.subject

        || item.document_subject

        || item.regarding

        || 'No subject'

}

const notificationDate = (item) => {

    return item.confirmdate

        || item.received_at

        || item.received_date

        || item.distdate

        || item.transfer_date

        || item.starts_at

        || item.created_at

        || ''

}

const displayNotificationCount = computed(() => {

    return notificationItems.value.filter((item) => {

        return !isNotificationSeen(item)

    }).length

})

const openNotifications = () => {

    showNotifications.value = true

    fetchActiveAnnouncements()

}

const closeNotifications = () => {

    showNotifications.value = false

}

const logout = () => {

    showUserMenu.value = false

    router.post('/logout', {}, {

        preserveScroll: false,

        onFinish: () => {

            window.location.href = '/login'

        },

    })

}

const isAdminUser = computed(() => {

    return userRights.value === '1'

})

const isMonitoringUser = computed(() => {

    return userRights.value === '4'

})

const canViewAllDocuments = computed(() => {

    // All Documents module is only for Role 2.

    // In this module, Role 2 can see all documents,

    // but non-tagged documents are viewing-only on the details page.

    return userRights.value === '2'

})

const currentParams = computed(() => {

    const queryString = page.url.includes('?') ? page.url.split('?')[1] : ''

    return new URLSearchParams(queryString)

})

const activeSection = computed(() => {

    return currentParams.value.get('section') || ''

})

const activeFilter = computed(() => {

    return currentParams.value.get('filter') || ''

})

const incomingSections = [

    'incoming',

    'received-docs',

    'pending-docs',

    'pending-docs-07',

    'addressed-docs',

]

const outgoingSections = [

    'outgoing',

    'sent-docs',

    'pulled-out-docs',

]

const collaborationSections = [

    'collaboration',

]

const collaborationFilters = [

    'for-receiving',

    'received',

    'collab-received',

    'for-action',

    'addressed',

    'returned',

]

const isLibraryActive = computed(() => {

    return page.url.startsWith('/dts/library')

})





const isReportsActive = computed(() => {

    return activeSection.value === 'reports'

})

const isInventoryActive = computed(() => {

    return page.url.startsWith('/dts/inventory')

})

const isAboutActive = computed(() => {

    return activeSection.value === 'about'

})

const isAllDocumentsActive = computed(() => {

    return activeSection.value === 'all-documents'

})

const isAdminUsersActive = computed(() => {

    return page.url.startsWith('/admin/users')

})

const isIncomingActive = computed(() => {

    return incomingSections.includes(activeSection.value)

        || collaborationFilters.includes(activeFilter.value)

})

const isOutgoingActive = computed(() => {

    return outgoingSections.includes(activeSection.value)

})

const isCollaborationActive = computed(() => {

    return collaborationSections.includes(activeSection.value)

        || collaborationFilters.includes(activeFilter.value)

})

const isDocumentsActive = computed(() => {

    return page.url.startsWith('/dts')

        && !isLibraryActive.value

        && !isInventoryActive.value

        && !isReportsActive.value

        && !isAboutActive.value

        && !isAllDocumentsActive.value

        && !isIncomingActive.value

        && !isOutgoingActive.value

        && !isCollaborationActive.value

})

const navLinkClass = (active) => {

    return active

        ? 'bg-blue-600 text-white shadow-sm'

        : 'text-slate-300 hover:bg-slate-900 hover:text-white'

}

const emit = defineEmits([

    'openAddDocument',

    'open-add-document',

    'open-notifications',

])


/*
|--------------------------------------------------------------------------
| GLOBAL DTS GUIDE + ACCESSIBILITY
|--------------------------------------------------------------------------
|
| - Bilingual DTS Guide: English / Tagalog
| - Questions may still be typed in either language.
| - Floating guide button is draggable and its position is saved locally.
| - Accessibility text size remains available in the same panel.
|
*/

const DTS_ACCESSIBILITY_STORAGE_KEY =
    'dts_global_accessibility_v1'

const LEGACY_INVENTORY_ACCESSIBILITY_KEY =
    'dts_inventory_accessibility_v1'

const DTS_GUIDE_LANGUAGE_STORAGE_KEY =
    'dts_guide_language_v1'


const dtsAccessibilityRoot = ref(null)
const accessibilityTextSize = ref(100)

let accessibilityTextObserver = null
let accessibilityTextApplyFrame = null

const accessibilityTextElements = new Set()

/*
|--------------------------------------------------------------------------
| DTS GUIDE LANGUAGE
|--------------------------------------------------------------------------
*/

const guideOpen = ref(false)
const guideView = ref('guide')
const guideLanguage = ref('en')
const guideQuestion = ref('')
const guideScrollRef = ref(null)

let guideMessageId = 1

const guideUi = {
    en: {
        subtitle: 'Ask how to use the system',
        guideTab: 'DTS Guide',
        accessibilityTab: 'Accessibility',
        languageLabel: 'Language',
        english: 'English',
        tagalog: 'Tagalog',
        quickQuestions: 'Quick questions',
        clear: 'Clear',
        askPlaceholder: 'Ask DTS Guide…',
        disclaimer: 'Answers are based on the DTS system guide only.',
        welcomeTitle: 'Hi! I’m your DTS Guide.',
        welcomeText:
            'Ask me how to use the Document Tracking System. You can type in English or Tagalog.',
        fallbackTitle: 'I don’t have a guide for that yet.',
        fallbackText:
            'Try asking about Receiving, Address, Return, Transfer, Inventory, Reports, Library, Notifications, All Documents, or Monitoring.',
        textSize: 'Text Size',
        textSizeHelp: 'Adjust the text size across all DTS pages.',
        resetTextSize: 'Reset text size',
        backToGuide: 'Back to DTS Guide',
        openGuide: 'Open DTS Guide',
        closeGuide: 'Close DTS Guide',
        sendQuestion: 'Send question',
    },
    tl: {
        subtitle: 'Magtanong kung paano gamitin ang system',
        guideTab: 'DTS Guide',
        accessibilityTab: 'Accessibility',
        languageLabel: 'Wika',
        english: 'English',
        tagalog: 'Tagalog',
        quickQuestions: 'Mabilis na tanong',
        clear: 'Burahin',
        askPlaceholder: 'Magtanong sa DTS Guide…',
        disclaimer: 'Ang mga sagot ay batay lamang sa gabay ng DTS system.',
        welcomeTitle: 'Hi! Ako ang iyong DTS Guide.',
        welcomeText:
            'Magtanong kung paano gamitin ang Document Tracking System. Maaari kang magtanong sa English o Tagalog.',
        fallbackTitle: 'Wala pa akong gabay para sa tanong na iyan.',
        fallbackText:
            'Subukang magtanong tungkol sa Receiving, Address, Return, Transfer, Inventory, Reports, Library, Notifications, All Documents, o Monitoring.',
        textSize: 'Laki ng Teksto',
        textSizeHelp: 'Ayusin ang laki ng teksto sa lahat ng DTS pages.',
        resetTextSize: 'Ibalik ang laki ng teksto',
        backToGuide: 'Bumalik sa DTS Guide',
        openGuide: 'Buksan ang DTS Guide',
        closeGuide: 'Isara ang DTS Guide',
        sendQuestion: 'Ipadala ang tanong',
    },
}

const guideText = computed(
    () => guideUi[guideLanguage.value] || guideUi.en
)

const guideQuickQuestionsByLanguage = {
    en: [
        'How do I receive a document?',
        'How do I return a document?',
        'How do I add an inventory item?',
        'How do I release ICT equipment?',
    ],
    tl: [
        'Paano mag-receive ng dokumento?',
        'Paano mag-return ng dokumento?',
        'Paano mag-add ng inventory?',
        'Paano mag-release ng ICT?',
    ],
}

const guideQuickQuestions = computed(
    () =>
        guideQuickQuestionsByLanguage[
            guideLanguage.value
        ] || guideQuickQuestionsByLanguage.en
)

const guideTopics = [
    {
        id: 'receive-document',
        keywords: [
            'receive document',
            'receive ng document',
            'receive ng dokumento',
            'mag receive',
            'mag-receive',
            'receiving',
            'for receiving',
            'how do i receive',
            'how to receive',
            'paano mag receive',
            'paano mag-receive',
            'tanggapin document',
            'tanggap document',
            'tanggapin dokumento',
        ],
        title: {
            en: 'Receive a Document',
            tl: 'Paano Tumanggap ng Dokumento',
        },
        steps: {
            en: [
                'Open the For Receiving or Incoming section.',
                'Select the document you need to process.',
                'Review the document details before receiving it.',
                'Click Receive and confirm the action.',
                'After receiving, the document moves to the Received list.',
            ],
            tl: [
                'Buksan ang For Receiving o Incoming section.',
                'Piliin ang dokumentong kailangan mong i-process.',
                'Suriin muna ang detalye ng dokumento.',
                'I-click ang Receive at i-confirm ang action.',
                'Pagkatapos ma-receive, mapupunta ang dokumento sa Received list.',
            ],
        },
        href: '/dts?section=collaboration&filter=for-receiving',
        actionLabel: {
            en: 'Open For Receiving',
            tl: 'Buksan ang For Receiving',
        },
    },
    {
        id: 'address-document',
        keywords: [
            'address document',
            'addressed',
            'mag address',
            'mag-address',
            'first action',
            'final action',
            'for action',
            'how to address',
            'paano i address',
            'paano mag address',
        ],
        title: {
            en: 'Address a Document',
            tl: 'Paano Mag-Address ng Dokumento',
        },
        steps: {
            en: [
                'Open the document that is already Received or ready for action.',
                'Choose Address from the available document actions.',
                'Select First Action and/or Final Action when applicable.',
                'Enter the required remarks.',
                'Save the action. The document will appear under Addressed after the action is completed.',
            ],
            tl: [
                'Buksan ang dokumentong Received na o handa nang aksyunan.',
                'Piliin ang Address mula sa document actions.',
                'Piliin ang First Action at/o Final Action kung naaangkop.',
                'Ilagay ang required remarks.',
                'I-save ang action. Lalabas ang dokumento sa Addressed kapag nakumpleto ang action.',
            ],
        },
        href: '/dts?section=collaboration&filter=received',
        actionLabel: {
            en: 'Open Received',
            tl: 'Buksan ang Received',
        },
    },
    {
        id: 'return-document',
        keywords: [
            'return document',
            'return to admin',
            'returned',
            'mag return',
            'mag-return',
            'ibalik document',
            'ibalik dokumento',
            'how to return',
            'paano mag return',
            'paano mag-return',
        ],
        title: {
            en: 'Return a Document',
            tl: 'Paano Mag-Return ng Dokumento',
        },
        steps: {
            en: [
                'Open the document you want to return.',
                'Choose Return from the document actions.',
                'Enter the required remarks explaining the return.',
                'Confirm the action.',
                'The returned document will appear in the Returned workflow until it is received again by the appropriate user.',
            ],
            tl: [
                'Buksan ang dokumentong gusto mong i-return.',
                'Piliin ang Return mula sa document actions.',
                'Ilagay ang required remarks at dahilan ng pag-return.',
                'I-confirm ang action.',
                'Lalabas ang dokumento sa Returned workflow hanggang ma-receive ulit ito ng tamang user.',
            ],
        },
        href: '/dts?section=collaboration&filter=returned',
        actionLabel: {
            en: 'Open Returned',
            tl: 'Buksan ang Returned',
        },
    },
    {
        id: 'transfer-document',
        keywords: [
            'transfer document',
            'mag transfer',
            'mag-transfer',
            'forward document',
            'ilipat document',
            'ilipat dokumento',
            'how to transfer',
            'paano mag transfer',
            'paano mag-transfer',
        ],
        title: {
            en: 'Transfer a Document',
            tl: 'Paano Mag-Transfer ng Dokumento',
        },
        steps: {
            en: [
                'Open the document you want to transfer.',
                'Choose Transfer from the document actions.',
                'Complete the required destination or receiving information.',
                'Enter the required remarks.',
                'Confirm the transfer, then check the document history to verify the action.',
            ],
            tl: [
                'Buksan ang dokumentong gusto mong i-transfer.',
                'Piliin ang Transfer mula sa document actions.',
                'Kumpletuhin ang required destination o receiving information.',
                'Ilagay ang required remarks.',
                'I-confirm ang transfer at tingnan ang document history para ma-verify ang action.',
            ],
        },
        href: '/dts?section=incoming',
        actionLabel: {
            en: 'Open Incoming',
            tl: 'Buksan ang Incoming',
        },
    },
    {
        id: 'inventory-add',
        keywords: [
            'add inventory',
            'add item inventory',
            'new inventory',
            'add inventory item',
            'mag add inventory',
            'mag-add inventory',
            'dagdag inventory',
            'how to add inventory',
            'paano mag add ng inventory',
            'paano mag-add ng inventory',
            'furniture inventory',
            'ict inventory',
            'supplies inventory',
        ],
        title: {
            en: 'Add an Inventory Item',
            tl: 'Paano Magdagdag ng Inventory Item',
        },
        steps: {
            en: [
                'Open Inventory from the sidebar.',
                'Choose Supplies, ICT, or Other Items.',
                'Click Add Item.',
                'Complete the required item information for the selected category.',
                'For property-tracked ICT or Furniture/Fixtures, complete the required Property Details.',
                'Click Save when all required fields are complete.',
            ],
            tl: [
                'Buksan ang Inventory mula sa sidebar.',
                'Piliin ang Supplies, ICT, o Other Items.',
                'I-click ang Add Item.',
                'Kumpletuhin ang required item information para sa napiling category.',
                'Para sa property-tracked ICT o Furniture/Fixtures, kumpletuhin ang required Property Details.',
                'I-click ang Save kapag kumpleto na ang required fields.',
            ],
        },
        href: '/dts/inventory',
        actionLabel: {
            en: 'Open Inventory',
            tl: 'Buksan ang Inventory',
        },
    },
    {
        id: 'inventory-release',
        keywords: [
            'release inventory',
            'release ict',
            'release supplies',
            'release furniture',
            'release equipment',
            'mag release inventory',
            'mag-release inventory',
            'how to release',
            'paano mag release',
            'paano mag-release',
            'property number release',
        ],
        title: {
            en: 'Release an Inventory Item',
            tl: 'Paano Mag-Release ng Inventory Item',
        },
        steps: {
            en: [
                'Open Inventory and locate the item.',
                'Choose the Release action for that item.',
                'Enter the quantity or duration required by the item type.',
                'For property-tracked ICT or Furniture/Fixtures, select the Property Number being released and enter the destination/current user.',
                'Add remarks when needed, then confirm the release.',
                'Check History if you need to review the recorded transaction.',
            ],
            tl: [
                'Buksan ang Inventory at hanapin ang item.',
                'Piliin ang Release action para sa item.',
                'Ilagay ang quantity o duration na kailangan ayon sa item type.',
                'Para sa property-tracked ICT o Furniture/Fixtures, piliin ang Property Number na ire-release at ilagay ang destination/current user.',
                'Maglagay ng remarks kung kailangan, pagkatapos ay i-confirm ang release.',
                'Tingnan ang History kung kailangan mong i-review ang recorded transaction.',
            ],
        },
        href: '/dts/inventory',
        actionLabel: {
            en: 'Open Inventory',
            tl: 'Buksan ang Inventory',
        },
    },
    {
        id: 'reports',
        keywords: [
            'report',
            'reports',
            'generate report',
            'print report',
            'how to report',
            'paano mag report',
            'paano mag-report',
            'report by date',
        ],
        title: {
            en: 'Generate or View Reports',
            tl: 'Paano Tingnan o Gumawa ng Report',
        },
        steps: {
            en: [
                'Open Reports from the sidebar.',
                'Choose the report type or filters you need.',
                'Use the available search and date filters to narrow the records.',
                'Review the displayed results.',
                'Use the print option when you need a printable copy.',
            ],
            tl: [
                'Buksan ang Reports mula sa sidebar.',
                'Piliin ang report type o filters na kailangan mo.',
                'Gamitin ang search at date filters para paliitin ang listahan ng records.',
                'Suriin ang displayed results.',
                'Gamitin ang print option kung kailangan mo ng printable copy.',
            ],
        },
        href: '/dts?section=reports&type=by-date',
        actionLabel: {
            en: 'Open Reports',
            tl: 'Buksan ang Reports',
        },
    },
    {
        id: 'library',
        keywords: [
            'library',
            'personnel library',
            'office library',
            'doc type',
            'document type',
            'attachment library',
            'how to use library',
            'paano library',
        ],
        title: {
            en: 'Use the DTS Library',
            tl: 'Paano Gamitin ang DTS Library',
        },
        steps: {
            en: [
                'Open Library from the sidebar.',
                'Choose the library section you need, such as Personnel, Office, Doc Type, or Attachment.',
                'Use search and pagination to locate an entry.',
                'Available actions depend on the selected library section and your access rights.',
            ],
            tl: [
                'Buksan ang Library mula sa sidebar.',
                'Piliin ang kailangan mong section gaya ng Personnel, Office, Doc Type, o Attachment.',
                'Gamitin ang search at pagination para mahanap ang entry.',
                'Ang available actions ay nakadepende sa napiling library section at access rights mo.',
            ],
        },
        href: '/dts/library',
        actionLabel: {
            en: 'Open Library',
            tl: 'Buksan ang Library',
        },
    },
    {
        id: 'notifications',
        keywords: [
            'notification',
            'notifications',
            'bell',
            'announcement',
            'alerts',
            'unread',
            'how to check notification',
            'paano notification',
        ],
        title: {
            en: 'Check DTS Notifications',
            tl: 'Paano Tingnan ang DTS Notifications',
        },
        steps: {
            en: [
                'Click the notification bell in the top bar.',
                'Unread announcements and document alerts will appear in the notification panel.',
                'For announcements, use Mark as read after reviewing them.',
                'For document alerts, click View Details to open the related document.',
            ],
            tl: [
                'I-click ang notification bell sa top bar.',
                'Lalabas sa notification panel ang unread announcements at document alerts.',
                'Para sa announcements, gamitin ang Mark as read pagkatapos basahin.',
                'Para sa document alerts, i-click ang View Details para buksan ang kaugnay na dokumento.',
            ],
        },
        href: '/dts',
        actionLabel: {
            en: 'Open Dashboard',
            tl: 'Buksan ang Dashboard',
        },
    },
    {
        id: 'all-documents',
        keywords: [
            'all documents',
            'lahat ng documents',
            'lahat ng document',
            'lahat ng dokumento',
            'view all document',
            'search document',
            'find document',
        ],
        title: {
            en: 'View All Documents',
            tl: 'Paano Tingnan ang Lahat ng Dokumento',
        },
        steps: {
            en: [
                'Open All Documents from the sidebar if your role has access.',
                'Use the available search or filters to locate the document.',
                'Open a record to review its details and history.',
                'Actions available on the details page still depend on your role and the document workflow.',
            ],
            tl: [
                'Buksan ang All Documents mula sa sidebar kung may access ang role mo.',
                'Gamitin ang available search o filters para mahanap ang dokumento.',
                'Buksan ang record para makita ang details at history nito.',
                'Ang available actions sa details page ay nakadepende pa rin sa role mo at document workflow.',
            ],
        },
        href: '/dts?section=all-documents',
        actionLabel: {
            en: 'Open All Documents',
            tl: 'Buksan ang All Documents',
        },
    },
    {
        id: 'monitoring-dashboard',
        keywords: [
            'monitoring dashboard',
            'monitoring',
            'admin dashboard',
            'dashboard monitoring',
            'how to open monitoring',
            'paano monitoring',
        ],
        title: {
            en: 'Open the Monitoring Dashboard',
            tl: 'Paano Buksan ang Monitoring Dashboard',
        },
        steps: {
            en: [
                'Open the account menu in the top-right corner.',
                'If your account has Monitoring access, choose Admin Dashboard.',
                'Use the monitoring cards and lists to review the current document workflow status.',
            ],
            tl: [
                'Buksan ang account menu sa kanang itaas.',
                'Kung may Monitoring access ang account mo, piliin ang Admin Dashboard.',
                'Gamitin ang monitoring cards at lists para makita ang kasalukuyang document workflow status.',
            ],
        },
        href: '/dts/monitoring-dashboard',
        actionLabel: {
            en: 'Open Monitoring Dashboard',
            tl: 'Buksan ang Monitoring Dashboard',
        },
    },
    {
        id: 'status-addressed',
        keywords: [
            'what is addressed',
            'what does addressed mean',
            'ano addressed',
            'meaning addressed',
            'ibig sabihin addressed',
            'addressed status',
        ],
        title: {
            en: 'What does Addressed mean?',
            tl: 'Ano ang ibig sabihin ng Addressed?',
        },
        steps: {
            en: [
                'Addressed means the required document action has already been recorded.',
                'The document is no longer waiting in the Returned or pending-action workflow once its addressing action is completed.',
                'Open the document details or history if you need to review the exact action and remarks.',
            ],
            tl: [
                'Ang Addressed ay nangangahulugang na-record na ang kinakailangang action para sa dokumento.',
                'Hindi na naghihintay ang dokumento sa Returned o pending-action workflow kapag kumpleto na ang addressing action.',
                'Buksan ang document details o history kung kailangan mong makita ang eksaktong action at remarks.',
            ],
        },
        href: '/dts?section=collaboration&filter=addressed',
        actionLabel: {
            en: 'Open Addressed',
            tl: 'Buksan ang Addressed',
        },
    },
]

const createGuideWelcomeMessage = () => ({
    id: guideMessageId++,
    role: 'assistant',
    kind: 'welcome',
})

const guideMessages = ref([
    createGuideWelcomeMessage(),
])

const setGuideLanguage = (language) => {
    if (!['en', 'tl'].includes(language)) {
        return
    }

    guideLanguage.value = language

    if (typeof window !== 'undefined') {
        try {
            window.localStorage.setItem(
                DTS_GUIDE_LANGUAGE_STORAGE_KEY,
                language
            )
        } catch (error) {
            // Keep the selected language for the current session.
        }
    }

}

const loadGuideLanguage = () => {
    if (typeof window === 'undefined') {
        return
    }

    try {
        const saved =
            window.localStorage.getItem(
                DTS_GUIDE_LANGUAGE_STORAGE_KEY
            )

        if (['en', 'tl'].includes(saved)) {
            guideLanguage.value = saved
        }
    } catch (error) {
        guideLanguage.value = 'en'
    }
}

const guideTopicById = (topicId) => {
    return guideTopics.find(
        (topic) => topic.id === topicId
    ) || null
}

const guideMessageTitle = (message) => {
    if (message?.kind === 'welcome') {
        return guideText.value.welcomeTitle
    }

    if (message?.kind === 'fallback') {
        return guideText.value.fallbackTitle
    }

    const topic =
        guideTopicById(message?.topicId)

    return (
        topic?.title?.[guideLanguage.value]
        || topic?.title?.en
        || ''
    )
}

const guideMessageText = (message) => {
    if (message?.kind === 'welcome') {
        return guideText.value.welcomeText
    }

    if (message?.kind === 'fallback') {
        return guideText.value.fallbackText
    }

    return ''
}

const guideMessageSteps = (message) => {
    const topic =
        guideTopicById(message?.topicId)

    return (
        topic?.steps?.[guideLanguage.value]
        || topic?.steps?.en
        || []
    )
}

const guideMessageHref = (message) => {
    const topic =
        guideTopicById(message?.topicId)

    return topic?.href || null
}

const guideMessageActionLabel = (message) => {
    const topic =
        guideTopicById(message?.topicId)

    return (
        topic?.actionLabel?.[
            guideLanguage.value
        ]
        || topic?.actionLabel?.en
        || ''
    )
}

const normalizeGuideQuery = (value) => {
    return String(value || '')
        .toLowerCase()
        .replace(
            /[^a-z0-9\u00c0-\u024f\s-]/g,
            ' '
        )
        .replace(/\s+/g, ' ')
        .trim()
}

const guideTopicScore = (
    topic,
    normalizedQuestion
) => {
    if (!normalizedQuestion) {
        return 0
    }

    let score = 0

    topic.keywords.forEach((keyword) => {
        const normalizedKeyword =
            normalizeGuideQuery(keyword)

        if (
            normalizedKeyword
            && normalizedQuestion.includes(
                normalizedKeyword
            )
        ) {
            score +=
                10
                + normalizedKeyword
                    .split(' ')
                    .length
        }
    })

    const stopWords = new Set([
        'paano',
        'where',
        'what',
        'when',
        'which',
        'yung',
        'ang',
        'para',
        'with',
        'from',
        'this',
        'that',
        'does',
        'isang',
        'mga',
    ])

    const questionWords =
        new Set(
            normalizedQuestion
                .split(' ')
                .filter(
                    (word) =>
                        word.length >= 4
                        && !stopWords.has(word)
                )
        )

    topic.keywords.forEach((keyword) => {
        normalizeGuideQuery(keyword)
            .split(' ')
            .forEach((word) => {
                if (
                    word.length >= 4
                    && questionWords.has(word)
                ) {
                    score += 1
                }
            })
    })

    return score
}

const findGuideTopic = (question) => {
    const normalizedQuestion =
        normalizeGuideQuery(question)

    if (!normalizedQuestion) {
        return null
    }

    let bestTopic = null
    let bestScore = 0

    guideTopics.forEach((topic) => {
        const score =
            guideTopicScore(
                topic,
                normalizedQuestion
            )

        if (score > bestScore) {
            bestScore = score
            bestTopic = topic
        }
    })

    return bestScore >= 2
        ? bestTopic
        : null
}

const scrollGuideToBottom = async () => {
    await nextTick()

    if (!guideScrollRef.value) {
        return
    }

    guideScrollRef.value.scrollTop =
        guideScrollRef.value.scrollHeight
}

/*
|--------------------------------------------------------------------------
| GUIDE PANEL ACTIONS
|--------------------------------------------------------------------------
*/

const openGuidePanel = async () => {
    guideOpen.value = true
    guideView.value = 'guide'
    showUserMenu.value = false
    showNotifications.value = false

    await nextTick()
    scrollGuideToBottom()
}

const toggleGuidePanel = () => {
    if (guideOpen.value) {
        guideOpen.value = false
        return
    }

    openGuidePanel()
}

const closeGuidePanel = () => {
    guideOpen.value = false
}

const showGuideAccessibility = async () => {
    guideOpen.value = true
    guideView.value = 'accessibility'
    showUserMenu.value = false
    showNotifications.value = false

    await nextTick()
}

const showGuideChat = async () => {
    guideView.value = 'guide'

    await nextTick()
    scrollGuideToBottom()
}

const clearGuideConversation = () => {
    guideMessages.value = [
        createGuideWelcomeMessage(),
    ]

    guideQuestion.value = ''
    scrollGuideToBottom()
}

const submitGuideQuestion = (
    question = guideQuestion.value
) => {
    const cleanQuestion =
        String(question || '').trim()

    if (!cleanQuestion) {
        return
    }

    guideMessages.value.push({
        id: guideMessageId++,
        role: 'user',
        text: cleanQuestion,
    })

    const topic =
        findGuideTopic(cleanQuestion)

    if (topic) {
        guideMessages.value.push({
            id: guideMessageId++,
            role: 'assistant',
            kind: 'topic',
            topicId: topic.id,
        })
    } else {
        guideMessages.value.push({
            id: guideMessageId++,
            role: 'assistant',
            kind: 'fallback',
        })
    }

    guideQuestion.value = ''

    scrollGuideToBottom()
}

const askGuideQuickQuestion = (
    question
) => {
    submitGuideQuestion(question)
}

const openGuideDestination = (
    message
) => {
    const href =
        String(
            guideMessageHref(message)
            || ''
        ).trim()

    if (!href) {
        return
    }

    guideOpen.value = false

    router.visit(
        href,
        {
            preserveScroll: false,
        }
    )
}

/*
|--------------------------------------------------------------------------
| ACCESSIBILITY
|--------------------------------------------------------------------------
*/

const clampAccessibilityTextSize = (
    value
) => {
    const number = Number(value)

    if (!Number.isFinite(number)) {
        return 100
    }

    return Math.min(
        200,
        Math.max(
            80,
            Math.round(number / 10) * 10
        )
    )
}

const decreaseAccessibilityTextSize = () => {
    accessibilityTextSize.value =
        clampAccessibilityTextSize(
            accessibilityTextSize.value - 10
        )
}

const increaseAccessibilityTextSize = () => {
    accessibilityTextSize.value =
        clampAccessibilityTextSize(
            accessibilityTextSize.value + 10
        )
}

const resetAccessibilityTextSize = () => {
    accessibilityTextSize.value = 100
    showGuideAccessibility()
}

const saveAccessibilitySettings = () => {
    if (typeof window === 'undefined') {
        return
    }

    try {
        window.localStorage.setItem(
            DTS_ACCESSIBILITY_STORAGE_KEY,
            JSON.stringify({
                textSize:
                    accessibilityTextSize.value,
            })
        )
    } catch (error) {
        // Keep accessibility working for the current session.
    }
}

const loadAccessibilitySettings = () => {
    if (typeof window === 'undefined') {
        return
    }

    try {
        const raw =
            window.localStorage.getItem(
                DTS_ACCESSIBILITY_STORAGE_KEY
            )
            || window.localStorage.getItem(
                LEGACY_INVENTORY_ACCESSIBILITY_KEY
            )

        if (!raw) {
            return
        }

        const saved = JSON.parse(raw)

        accessibilityTextSize.value =
            clampAccessibilityTextSize(
                saved?.textSize
            )
    } catch (error) {
        accessibilityTextSize.value = 100
    }
}

const restoreAccessibilityElementFont = (
    element
) => {
    if (!(element instanceof HTMLElement)) {
        return
    }

    const originalValue =
        element.dataset
            .a11yOriginalFontSize ?? ''

    const originalPriority =
        element.dataset
            .a11yOriginalFontPriority ?? ''

    if (originalValue) {
        element.style.setProperty(
            'font-size',
            originalValue,
            originalPriority
        )
    } else {
        element.style.removeProperty(
            'font-size'
        )
    }

    delete element.dataset
        .a11yBaseFontSize
    delete element.dataset
        .a11yOriginalFontSize
    delete element.dataset
        .a11yOriginalFontPriority

    accessibilityTextElements.delete(
        element
    )
}

const restoreAllAccessibilityTextSizes =
    () => {
        accessibilityTextElements
            .forEach((element) => {
                restoreAccessibilityElementFont(
                    element
                )
            })

        accessibilityTextElements.clear()
    }

const isAccessibilityTextElement = (
    element
) => {
    if (!(element instanceof HTMLElement)) {
        return false
    }

    if (
        element.closest(
            '.dts-accessibility-ui'
        )
    ) {
        return false
    }

    if (
        element.matches(
            'input, textarea, select, option'
        )
    ) {
        return true
    }

    return Array.from(
        element.childNodes
    ).some((node) => {
        return (
            node.nodeType === Node.TEXT_NODE
            && String(
                node.textContent || ''
            ).trim() !== ''
        )
    })
}

const scaleAccessibilityElementFont = (
    element,
    scale
) => {
    if (
        !(element instanceof HTMLElement)
        || !isAccessibilityTextElement(
            element
        )
    ) {
        return
    }

    if (
        !element.dataset.a11yBaseFontSize
    ) {
        const computedFontSize =
            Number.parseFloat(
                window.getComputedStyle(
                    element
                ).fontSize
            )

        if (
            !Number.isFinite(
                computedFontSize
            )
            || computedFontSize <= 0
        ) {
            return
        }

        element.dataset.a11yBaseFontSize =
            String(computedFontSize)

        element.dataset
            .a11yOriginalFontSize =
            element.style.getPropertyValue(
                'font-size'
            )

        element.dataset
            .a11yOriginalFontPriority =
            element.style
                .getPropertyPriority(
                    'font-size'
                )

        accessibilityTextElements.add(
            element
        )
    }

    const baseFontSize =
        Number(
            element.dataset
                .a11yBaseFontSize
        )

    if (!Number.isFinite(baseFontSize)) {
        return
    }

    if (scale === 1) {
        const originalValue =
            element.dataset
                .a11yOriginalFontSize ?? ''

        const originalPriority =
            element.dataset
                .a11yOriginalFontPriority ?? ''

        if (originalValue) {
            element.style.setProperty(
                'font-size',
                originalValue,
                originalPriority
            )
        } else {
            element.style.removeProperty(
                'font-size'
            )
        }

        return
    }

    element.style.setProperty(
        'font-size',
        `${Math.max(
            1,
            baseFontSize * scale
        )}px`,
        'important'
    )
}

const applyAccessibilityTextSize = () => {
    if (
        typeof window === 'undefined'
        || !dtsAccessibilityRoot.value
    ) {
        return
    }

    accessibilityTextElements
        .forEach((element) => {
            if (!element.isConnected) {
                accessibilityTextElements
                    .delete(element)
            }
        })

    const scale =
        clampAccessibilityTextSize(
            accessibilityTextSize.value
        ) / 100

    dtsAccessibilityRoot.value
        .querySelectorAll('*')
        .forEach((element) => {
            scaleAccessibilityElementFont(
                element,
                scale
            )
        })
}

const scheduleAccessibilityTextSize = () => {
    if (typeof window === 'undefined') {
        return
    }

    if (accessibilityTextApplyFrame) {
        window.cancelAnimationFrame(
            accessibilityTextApplyFrame
        )
    }

    accessibilityTextApplyFrame =
        window.requestAnimationFrame(() => {
            accessibilityTextApplyFrame =
                null

            applyAccessibilityTextSize()
        })
}

const startAccessibilityTextObserver = () => {
    if (
        typeof MutationObserver
            === 'undefined'
        || !dtsAccessibilityRoot.value
    ) {
        return
    }

    accessibilityTextObserver =
        new MutationObserver(
            (mutations) => {
                const pageChanged =
                    mutations.some(
                        (mutation) =>
                            mutation
                                .addedNodes
                                .length > 0
                            || mutation
                                .removedNodes
                                .length > 0
                    )

                if (pageChanged) {
                    scheduleAccessibilityTextSize()
                }
            }
        )

    accessibilityTextObserver.observe(
        dtsAccessibilityRoot.value,
        {
            childList: true,
            subtree: true,
        }
    )
}

const handleGuideKeydown = (event) => {
    if (event?.key === 'Escape') {
        closeGuidePanel()
    }
}

watch(
    accessibilityTextSize,
    () => {
        saveAccessibilitySettings()
        scheduleAccessibilityTextSize()
    }
)

watch(
    guideLanguage,
    async () => {
        await nextTick()
        }
)

onMounted(async () => {
    loadAccessibilitySettings()
    loadGuideLanguage()

    await nextTick()

    scheduleAccessibilityTextSize()
    startAccessibilityTextObserver()

    if (typeof window !== 'undefined') {
        window.addEventListener(
            'keydown',
            handleGuideKeydown
        )

    }
})

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener(
            'keydown',
            handleGuideKeydown
        )

    }

    if (accessibilityTextObserver) {
        accessibilityTextObserver
            .disconnect()

        accessibilityTextObserver = null
    }

    if (
        typeof window !== 'undefined'
        && accessibilityTextApplyFrame
    ) {
        window.cancelAnimationFrame(
            accessibilityTextApplyFrame
        )

        accessibilityTextApplyFrame = null
    }

    restoreAllAccessibilityTextSizes()
})

</script>

<template>

    <div
        ref="dtsAccessibilityRoot"
        class="dts-accessibility-root min-h-screen bg-slate-100 text-slate-800"
    >

        <div class="flex min-h-screen">

            <!-- MOBILE SIDEBAR OVERLAY -->

            <button

                v-if="showMobileSidebar"

                type="button"

                aria-label="Close navigation menu"

                class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden"

                @click="closeMobileSidebar"

            ></button>

            <!-- SIDE NAV BAR -->

            <aside

                class="fixed inset-y-0 left-0 z-50 flex w-[min(20rem,88vw)] -translate-x-full flex-col border-r border-slate-800 bg-slate-950 text-slate-200 shadow-2xl transition-transform duration-300 ease-out lg:z-30 lg:w-80 lg:translate-x-0 lg:shadow-none"

                :class="showMobileSidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"

            >

                <!-- Logo / Title -->

                <div class="border-b border-slate-800 px-4 py-4 sm:px-6 sm:py-6">

                    <div class="flex items-center gap-3 sm:gap-4">

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white p-1.5 shadow-sm sm:h-16 sm:w-16 sm:p-2">

                            <img

                                src="/images/logo_dts-nobg.png"

                                alt="Pantalan Logo"

                                class="h-10 w-10 object-contain sm:h-14 sm:w-14"

                            />

                        </div>

                        <div>

                            <p class="text-sm font-bold uppercase tracking-wide text-white">

                                DTS

                            </p>

                            <p class="text-xs font-semibold text-slate-400">

                                Document Tracking System

                            </p>

                        </div>

                        <button

                            type="button"

                            aria-label="Close navigation menu"

                            class="ml-auto inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-slate-900 text-xl font-black text-white hover:bg-slate-800 lg:hidden"

                            @click="closeMobileSidebar"

                        >

                            ×

                        </button>

                    </div>

                </div>

                <!-- Menu -->

                <nav class="flex-1 overflow-y-auto px-4 py-5">

                    <div class="space-y-3">

                        <Link

                            href="/dts"

                            @click="closeMobileSidebar"

                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"

                            :class="navLinkClass(isDocumentsActive)"

                        >

                            <span>Dashboard</span>

                            <span

                                v-if="isDocumentsActive"

                                class="text-xs font-bold"

                            >

                            </span>

                        </Link>

                        <Link

                            v-if="canViewAllDocuments"

                            href="/dts?section=all-documents"

                            @click="closeMobileSidebar"

                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"

                            :class="navLinkClass(isAllDocumentsActive)"

                        >

                            <span>All Documents</span>

                            <span

                                v-if="isAllDocumentsActive"

                                class="text-xs font-bold"

                            >

                            </span>

                        </Link>

                        <Link

                            href="/dts?section=incoming"

                            @click="closeMobileSidebar"

                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"

                            :class="navLinkClass(isIncomingActive)"

                        >

                            <span>Incoming</span>

                            <span

                                v-if="isIncomingActive"

                                class="text-xs font-bold"

                            >

                            </span>

                        </Link>

                        <!-- <Link

                            href="/dts?section=sent-docs"

                            @click="closeMobileSidebar"

                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"

                            :class="navLinkClass(isOutgoingActive)"

                        >

                            <span>Outgoing</span>

                            <span

                                v-if="isOutgoingActive"

                                class="text-xs font-bold"

                            >   

                            </span>

                        </Link> -->



                        <Link

                            href="/dts/library"

                            @click="closeMobileSidebar"

                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"

                            :class="navLinkClass(isLibraryActive)"

                        >

                            <span>Library</span>

                            <span

                                v-if="isLibraryActive"

                                class="text-xs font-bold"

                            >

                            </span>

                        </Link>

                        <Link

                            href="/dts?section=reports&type=by-date"

                            @click="closeMobileSidebar"

                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"

                            :class="navLinkClass(isReportsActive)"

                        >

                            <span>Reports</span>

                            <span

                                v-if="isReportsActive"

                                class="text-xs font-bold"

                            >

                            </span>

                        </Link>

                        <Link

                            href="/dts/inventory"

                            @click="closeMobileSidebar" 

                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"

                            :class="navLinkClass(isInventoryActive)"

                        >

                            <span class="flex items-center gap-3">

                                <span>Inventory</span>

                            </span>

                        </Link>



                        <Link

                            href="/dts?section=about"

                            @click="closeMobileSidebar"

                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"

                            :class="navLinkClass(isAboutActive)"

                        >

                            <span>About</span>

                            <span

                                v-if="isAboutActive"

                                class="text-xs font-bold">

                            </span>

                        </Link>

                    </div>

                </nav>

            </aside>

            <!-- RIGHT CONTENT -->

            <div class="min-w-0 flex-1 pl-0 lg:pl-80">

                <!-- TOP USER BAR -->

                <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-3 py-3 shadow-sm backdrop-blur sm:px-6 sm:py-4">

                    <div class="flex items-center justify-between gap-3">

                        <button

                            type="button"

                            aria-label="Open navigation menu"

                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-blue-100 bg-blue-50 text-xl font-black text-blue-700 shadow-sm hover:bg-blue-100 lg:hidden"

                            @click="openMobileSidebar"

                        >

                            ☰

                        </button>

                        <div class="ml-auto flex min-w-0 items-center justify-end gap-2 sm:gap-3">

                        <!-- Notification Bell -->

                        <button

                            type="button"

                            class="group relative inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl sm:h-12 sm:w-12 bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 text-white shadow-lg shadow-blue-200/80 ring-1 ring-blue-400/40 transition-all duration-200 hover:-translate-y-0.5 hover:scale-[1.03] hover:from-blue-600 hover:via-blue-700 hover:to-indigo-800 hover:shadow-xl hover:shadow-blue-300/70 focus:outline-none focus:ring-4 focus:ring-blue-200"

                            title="Notifications"

                            @click="openNotifications"

                        >

                            <span class="pointer-events-none absolute inset-1 rounded-xl bg-white/10 opacity-0 transition group-hover:opacity-100"></span>

                            <svg

                                xmlns="http://www.w3.org/2000/svg"

                                viewBox="0 0 24 24"

                                fill="currentColor"

                                class="relative z-10 h-5 w-5 drop-shadow-sm transition-transform duration-200 group-hover:rotate-12 group-hover:scale-110"

                            >

                                <path

                                    d="M12 2a6 6 0 0 0-6 6v3.586l-1.707 1.707A1 1 0 0 0 5 15h14a1 1 0 0 0 .707-1.707L18 11.586V8a6 6 0 0 0-6-6Z"

                                />

                                <path

                                    d="M9.25 17a2.75 2.75 0 0 0 5.5 0h-5.5Z"

                                />

                            </svg>

                            <span

                                v-if="displayNotificationCount > 0"

                                class="absolute -right-1.5 -top-1.5 z-20 flex h-6 min-w-6 items-center justify-center rounded-full border-2 border-white bg-rose-600 px-1.5 text-[10px] font-black leading-none text-white shadow-md shadow-rose-200 ring-2 ring-rose-100"

                            >

                                {{ displayNotificationCount > 99 ? '99+' : displayNotificationCount }}

                            </span>

                        </button>

                        <div class="relative">

                            <button

                                type="button"

                                class="flex h-11 min-w-0 items-center gap-2 rounded-2xl bg-blue-600 px-2.5 text-left text-white shadow-sm hover:bg-blue-700 sm:h-auto sm:gap-3 sm:px-4 sm:py-2.5"

                                @click="showUserMenu = !showUserMenu"

                            >

                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white text-sm font-bold text-blue-700">

                                    {{ userInitial }}

                                </span>

                                <span class="hidden min-w-0 sm:block">

                                    <span class="block text-xs font-semibold text-blue-100">

                                        Welcome back

                                    </span>

                                    <span class="block text-sm font-bold">

                                        {{ userDisplayName }}

                                    </span>

                                </span>

                                <span class="text-sm">

                                    {{ showUserMenu ? '⌃' : '⌄' }}

                                </span>

                            </button>

                            <div

                                v-if="showUserMenu"

                                class="fixed left-4 right-4 top-[4.75rem] z-50 max-h-[calc(100dvh-6rem)] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-3 sm:w-56"

                            >

                                <div class="border-b border-slate-100 px-4 py-3">

                                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400">

                                        Account

                                    </p>

                                    <p class="mt-1 text-sm font-bold text-slate-800">

                                        {{ userDisplayName }}

                                    </p>

                                </div>

                                <Link

                                    href="/profile"

                                    class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"

                                    @click="showUserMenu = false"

                                >

                                    <span>👤</span>

                                    <span>Profile</span>

                                </Link>

                                <Link

                                    v-if="isMonitoringUser"

                                    href="/dts/monitoring-dashboard"

                                    class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-blue-700 hover:bg-blue-50"

                                    @click="showUserMenu = false"

                                >

                                    <span>📊</span>

                                    <span>Admin Dashboard</span>

                                </Link>

                                <Link

                                    v-if="isAdminUser"

                                    href="/admin/users"

                                    class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"

                                    @click="showUserMenu = false"

                                >

                                    <span>🛡️</span>

                                    <span>Admin</span>

                                </Link>

                                <button

                                    type="button"

                                    class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-semibold text-red-600 hover:bg-red-50"

                                    @click="logout"

                                >

                                    <span>🚪</span>

                                    <span>Logout</span>

                                </button>

                            </div>

                        </div>

                    </div>

                    </div>

                </header>

                <slot />

                <!-- NOTIFICATION MODAL -->

                <div

                    v-if="showNotifications"

                    class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 px-0 py-0 backdrop-blur-sm sm:items-center sm:px-4 sm:py-8"

                    @click.self="closeNotifications"

                >

                    <div class="flex h-[100dvh] max-h-[100dvh] w-full max-w-3xl flex-col overflow-hidden rounded-none bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:rounded-[2rem]">

                        <div class="shrink-0 border-b border-blue-200 bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 px-4 py-4 text-white shadow-lg shadow-blue-200/40 sm:px-6 sm:py-5">

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">

                                <div>

                                    <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">

                                        Notifications

                                    </p>

                                    <h2 class="mt-2 text-2xl font-black">

                                        DTS Notifications

                                    </h2>

                                    <p class="mt-1 text-sm font-semibold text-blue-100">

                                        System announcements and document alerts.

                                    </p>

                                </div>

                                <button

                                    type="button"

                                    class="rounded-xl bg-white/15 px-4 py-2 text-sm font-black text-white hover:bg-white/25"

                                    @click="closeNotifications"

                                >

                                    Close

                                </button>

                            </div>

                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto bg-gradient-to-b from-blue-50/70 to-white p-4 sm:p-6">

                            <div

                                v-if="visibleNotificationItems.length"

                                class="space-y-4 sm:max-h-[58vh] sm:overflow-y-auto sm:pr-1"

                            >

                                <div

                                    v-for="(item, index) in visibleNotificationItems"

                                    :key="`layout-notification-${item.IDdoc || item.document_no || index}`"

                                    class="group overflow-hidden rounded-[1.5rem] border border-blue-100 bg-white shadow-sm shadow-blue-100/60 transition duration-200 hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-lg hover:shadow-blue-100"

                                >

                                    <div

                                        class="h-1.5"

                                        :class="item.notification_type === 'announcement'

                                            ? 'bg-violet-500'

                                            : item.notification_type === 'received_by_addressee'

                                                ? 'bg-emerald-500'

                                                : item.is_overdue

                                                    ? 'bg-red-500'

                                                    : 'bg-blue-500'"

                                    ></div>

                                    <div class="p-4 sm:p-5">

                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">

                                            <div class="min-w-0 flex-1">

                                                <div class="flex flex-wrap items-center gap-2">

                                                    <span

                                                        v-if="item.notification_type === 'announcement'"

                                                        class="rounded-full bg-violet-100 px-3 py-1 text-xs font-black text-violet-700"

                                                    >

                                                        📣 Announcement

                                                    </span>

                                                    <span

                                                        v-else-if="item.notification_type === 'received_by_addressee'"

                                                        class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-700"

                                                    >

                                                        ✓ Received

                                                    </span>

                                                    <span

                                                        v-else-if="item.is_overdue"

                                                        class="rounded-full bg-red-100 px-3 py-1 text-xs font-black text-red-700"

                                                    >

                                                        Overdue

                                                    </span>

                                                    <span

                                                        v-else

                                                        class="rounded-full bg-blue-100 px-3 py-1 text-xs font-black text-blue-700"

                                                    >

                                                        For Receiving

                                                    </span>

                                                    <span

                                                        v-if="item.notification_type !== 'announcement'"

                                                        class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700"

                                                    >

                                                        DTS #{{ item.document_no || item.IDdoc || '-' }}

                                                    </span>

                                                </div>

                                                <!-- <p class="mt-3 break-words text-xl font-black leading-8 text-slate-950">

                                                    <template v-if="item.notification_type === 'received_by_addressee'">

                                                        {{ notificationReceiverName(item) }} received DTS #{{ item.document_no || item.IDdoc || '-' }}

                                                    </template>

                                                    <template v-else-if="item.is_overdue">

                                                        DTS #{{ item.document_no || item.IDdoc || '-' }} is overdue

                                                    </template>

                                                    <template v-else>

                                                        DTS #{{ item.document_no || item.IDdoc || '-' }} is waiting to be received

                                                    </template>

                                                </p> -->

                                                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">

                                                    <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">

                                                        {{ item.notification_type === 'announcement' ? 'Announcement' : 'Document' }}

                                                    </p>

                                                    <p class="mt-1 break-words text-sm font-bold leading-6 text-slate-800">

                                                        {{ notificationSubject(item) }}

                                                    </p>

                                                    <p

                                                        v-if="item.notification_type === 'announcement' && item.message"

                                                        class="mt-2 whitespace-pre-wrap break-words text-sm font-semibold leading-6 text-slate-600"

                                                    >

                                                        {{ item.message }}

                                                    </p>

                                                </div>

                                               <p

                                                    v-if="notificationDate(item)"

                                                    class="mt-3 text-xs font-bold text-slate-500"

                                                >

                                                    {{ formatNotificationDate(notificationDate(item)) }}

                                                </p>

                                            </div>

                                            <div class="flex shrink-0 items-center sm:self-stretch">

                                                <button

                                                    v-if="item.notification_type === 'announcement'"

                                                    type="button"

                                                    class="inline-flex w-full items-center justify-center rounded-xl bg-violet-600 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-violet-700 sm:w-auto sm:py-2.5"

                                                    @click="markNotificationSeen(item)"

                                                >

                                                    Mark as read

                                                </button>

                                                <Link

                                                    v-else-if="item.IDdoc"

                                                    :href="`/dts/${item.IDdoc}`"

                                                    class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-blue-700 sm:w-auto sm:py-2.5"

                                                    @click="markNotificationSeen(item); closeNotifications()"

                                                >

                                                    View Details

                                                </Link>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div

                                v-else

                                class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">

                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-2xl shadow-sm">

                                    🔔

                                </div>

                                <h3 class="mt-4 text-xl font-black text-slate-900">

                                    No notifications

                                </h3>

                                <p class="mt-2 text-sm font-semibold text-slate-600">

                                    You have no unread announcements or document notifications.

                                </p>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- GLOBAL DTS GUIDE -->
    <div
        v-if="guideOpen"
        class="dts-accessibility-ui fixed bottom-[6.3rem] right-3 z-[9999] flex max-h-[min(720px,calc(100dvh-8rem))] w-[calc(100vw-1.5rem)] max-w-[470px] flex-col overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_28px_90px_rgba(15,23,42,0.26)] sm:right-6"
        role="dialog"
        aria-modal="false"
        aria-label="DTS Guide"
    >
        <!-- HEADER -->
        <div
            class="shrink-0 border-b border-blue-100 bg-gradient-to-r from-blue-600 via-blue-600 to-indigo-700 px-4 py-4 text-white sm:px-5"
        >
            <div
                class="flex items-start justify-between gap-3"
            >
                <div
                    class="flex min-w-0 items-center gap-3"
                >
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white p-1 shadow-sm"
                    >
                        <img
                            src="/images/blue_book_reading_icon_cropped.png"
                            alt=""
                            aria-hidden="true"
                            class="h-full w-full rounded-full object-contain"
                        />
                    </div>

                    <div class="min-w-0">
                        <h2
                            class="truncate text-lg font-black tracking-tight"
                        >
                            DTS Guide
                        </h2>

                        <p
                            class="mt-0.5 text-xs font-semibold text-blue-100"
                        >
                            {{ guideText.subtitle }}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/10 text-xl font-black text-white transition hover:bg-white/20"
                    :aria-label="
                        guideText.closeGuide
                    "
                    @click="closeGuidePanel"
                >
                    ×
                </button>
            </div>

            <!-- GUIDE / ACCESSIBILITY TABS -->
            <div
                class="mt-4 grid grid-cols-2 rounded-xl bg-blue-950/20 p-1"
            >
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-xs font-black transition"
                    :class="
                        guideView === 'guide'
                            ? 'bg-white text-blue-700 shadow-sm'
                            : 'text-blue-100 hover:bg-white/10 hover:text-white'
                    "
                    @click="showGuideChat"
                >
                    {{ guideText.guideTab }}
                </button>

                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-xs font-black transition"
                    :class="
                        guideView === 'accessibility'
                            ? 'bg-white text-blue-700 shadow-sm'
                            : 'text-blue-100 hover:bg-white/10 hover:text-white'
                    "
                    @click="
                        showGuideAccessibility
                    "
                >
                    {{
                        guideText.accessibilityTab
                    }}
                </button>
            </div>
        </div>

        <!-- GUIDE VIEW -->
        <template
            v-if="guideView === 'guide'"
        >
            <div
                ref="guideScrollRef"
                class="min-h-0 flex-1 overflow-y-auto bg-slate-50 px-4 py-4 sm:px-5"
            >
                <!-- LANGUAGE SWITCH -->
                <div
                    class="mb-3 flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm"
                >
                    <span
                        class="pl-2 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400"
                    >
                        {{ guideText.languageLabel }}
                    </span>

                    <div
                        class="grid grid-cols-2 rounded-xl bg-slate-100 p-1"
                    >
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-[11px] font-black transition"
                            :class="
                                guideLanguage === 'en'
                                    ? 'bg-white text-blue-700 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800'
                            "
                            @click="
                                setGuideLanguage('en')
                            "
                        >
                            English
                        </button>

                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-[11px] font-black transition"
                            :class="
                                guideLanguage === 'tl'
                                    ? 'bg-white text-blue-700 shadow-sm'
                                    : 'text-slate-500 hover:text-slate-800'
                            "
                            @click="
                                setGuideLanguage('tl')
                            "
                        >
                            Tagalog
                        </button>
                    </div>
                </div>

                <!-- QUICK QUESTIONS -->
                <div
                    class="mb-4 rounded-2xl border border-blue-100 bg-white p-3 shadow-sm"
                >
                    <div
                        class="flex items-center justify-between gap-3"
                    >
                        <p
                            class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400"
                        >
                            {{
                                guideText.quickQuestions
                            }}
                        </p>

                        <button
                            v-if="
                                guideMessages.length > 1
                            "
                            type="button"
                            class="text-[10px] font-black text-slate-400 transition hover:text-rose-600"
                            @click="
                                clearGuideConversation
                            "
                        >
                            {{ guideText.clear }}
                        </button>
                    </div>

                    <div
                        class="mt-2 flex flex-wrap gap-2"
                    >
                        <button
                            v-for="question in guideQuickQuestions"
                            :key="question"
                            type="button"
                            class="rounded-full border border-blue-100 bg-blue-50 px-3 py-2 text-left text-[11px] font-bold leading-4 text-blue-700 transition hover:border-blue-300 hover:bg-blue-100"
                            @click="
                                askGuideQuickQuestion(
                                    question
                                )
                            "
                        >
                            {{ question }}
                        </button>
                    </div>
                </div>

                <!-- CHAT MESSAGES -->
                <div class="space-y-3">
                    <div
                        v-for="message in guideMessages"
                        :key="message.id"
                        class="flex"
                        :class="
                            message.role === 'user'
                                ? 'justify-end'
                                : 'justify-start'
                        "
                    >
                        <!-- USER -->
                        <div
                            v-if="
                                message.role === 'user'
                            "
                            class="max-w-[86%] rounded-2xl rounded-br-md bg-blue-600 px-4 py-3 text-sm font-semibold leading-6 text-white shadow-sm"
                        >
                            {{ message.text }}
                        </div>

                        <!-- ASSISTANT -->
                        <div
                            v-else
                            class="max-w-[94%] rounded-2xl rounded-bl-md border border-slate-200 bg-white px-4 py-3 text-slate-700 shadow-sm"
                        >
                            <p
                                v-if="
                                    guideMessageTitle(
                                        message
                                    )
                                "
                                class="text-sm font-black text-slate-900"
                            >
                                {{
                                    guideMessageTitle(
                                        message
                                    )
                                }}
                            </p>

                            <p
                                v-if="
                                    guideMessageText(
                                        message
                                    )
                                "
                                class="mt-1 text-xs font-semibold leading-5 text-slate-600"
                            >
                                {{
                                    guideMessageText(
                                        message
                                    )
                                }}
                            </p>

                            <ol
                                v-if="
                                    guideMessageSteps(
                                        message
                                    ).length
                                "
                                class="mt-3 space-y-2"
                            >
                                <li
                                    v-for="(step, index) in guideMessageSteps(message)"
                                    :key="`${message.id}-step-${index}`"
                                    class="flex gap-2.5 text-xs font-semibold leading-5 text-slate-600"
                                >
                                    <span
                                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[10px] font-black text-blue-700"
                                    >
                                        {{ index + 1 }}
                                    </span>

                                    <span>
                                        {{ step }}
                                    </span>
                                </li>
                            </ol>

                            <button
                                v-if="
                                    guideMessageHref(
                                        message
                                    )
                                    && guideMessageActionLabel(
                                        message
                                    )
                                "
                                type="button"
                                class="mt-3 inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3.5 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-blue-700"
                                @click="
                                    openGuideDestination(
                                        message
                                    )
                                "
                            >
                                {{
                                    guideMessageActionLabel(
                                        message
                                    )
                                }}
                                <span
                                    aria-hidden="true"
                                >
                                    →
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHAT INPUT -->
            <form
                class="shrink-0 border-t border-slate-200 bg-white p-3 sm:p-4"
                @submit.prevent="
                    submitGuideQuestion()
                "
            >
                <div
                    class="flex items-end gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2 focus-within:border-blue-300 focus-within:ring-4 focus-within:ring-blue-50"
                >
                    <textarea
                        v-model="guideQuestion"
                        rows="1"
                        maxlength="500"
                        :placeholder="
                            guideText.askPlaceholder
                        "
                        class="max-h-28 min-h-[42px] flex-1 resize-none bg-transparent px-2 py-2.5 text-sm font-semibold leading-5 text-slate-800 outline-none placeholder:text-slate-400"
                        @keydown.enter.exact.prevent="
                            submitGuideQuestion()
                        "
                    ></textarea>

                    <button
                        type="submit"
                        :disabled="
                            !String(
                                guideQuestion || ''
                            ).trim()
                        "
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40"
                        :aria-label="
                            guideText.sendQuestion
                        "
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path
                                d="m22 2-7 20-4-9-9-4Z"
                            />
                            <path
                                d="M22 2 11 13"
                            />
                        </svg>
                    </button>
                </div>

                <p
                    class="mt-2 text-center text-[10px] font-semibold text-slate-400"
                >
                    {{ guideText.disclaimer }}
                </p>
            </form>
        </template>

        <!-- ACCESSIBILITY VIEW -->
        <div
            v-else
            class="min-h-0 flex-1 overflow-y-auto bg-white px-5 py-5"
        >
            <!-- LANGUAGE -->
            <div
                class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-3"
            >
                <p
                    class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400"
                >
                    {{ guideText.languageLabel }}
                </p>

                <div
                    class="mt-2 grid grid-cols-2 rounded-xl bg-slate-200/70 p-1"
                >
                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-xs font-black transition"
                        :class="
                            guideLanguage === 'en'
                                ? 'bg-white text-blue-700 shadow-sm'
                                : 'text-slate-500'
                        "
                        @click="
                            setGuideLanguage('en')
                        "
                    >
                        English
                    </button>

                    <button
                        type="button"
                        class="rounded-lg px-3 py-2 text-xs font-black transition"
                        :class="
                            guideLanguage === 'tl'
                                ? 'bg-white text-blue-700 shadow-sm'
                                : 'text-slate-500'
                        "
                        @click="
                            setGuideLanguage('tl')
                        "
                    >
                        Tagalog
                    </button>
                </div>
            </div>

            <!-- TEXT SIZE -->
            <div
                class="rounded-2xl border border-blue-100 bg-blue-50/60 p-4"
            >
                <p
                    class="text-xs font-black uppercase tracking-[0.08em] text-blue-700"
                >
                    {{ guideText.textSize }}
                </p>

                <p
                    class="mt-1 text-xs font-semibold leading-5 text-slate-500"
                >
                    {{ guideText.textSizeHelp }}
                </p>

                <div
                    class="mt-4 grid grid-cols-[58px_1fr_58px] gap-3"
                >
                    <button
                        type="button"
                        :disabled="
                            accessibilityTextSize <= 80
                        "
                        class="flex h-[52px] items-center justify-center rounded-2xl border border-slate-200 bg-white text-2xl font-black text-slate-600 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Decrease text size"
                        @click="
                            decreaseAccessibilityTextSize
                        "
                    >
                        −
                    </button>

                    <div
                        class="flex h-[52px] items-center justify-center rounded-2xl border border-blue-100 bg-white text-lg font-black tabular-nums text-slate-900 shadow-sm"
                    >
                        {{
                            accessibilityTextSize
                        }}%
                    </div>

                    <button
                        type="button"
                        :disabled="
                            accessibilityTextSize >= 200
                        "
                        class="flex h-[52px] items-center justify-center rounded-2xl border border-slate-200 bg-white text-2xl font-black text-slate-600 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Increase text size"
                        @click="
                            increaseAccessibilityTextSize
                        "
                    >
                        +
                    </button>
                </div>

                <button
                    type="button"
                    class="mt-4 flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 text-xs font-black text-rose-600 transition hover:bg-rose-100"
                    @click="
                        resetAccessibilityTextSize
                    "
                >
                    {{ guideText.resetTextSize }}
                </button>
            </div>

            <button
                type="button"
                class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-blue-200 bg-white px-4 py-3 text-xs font-black text-blue-700 transition hover:bg-blue-50"
                @click="showGuideChat"
            >
                ← {{ guideText.backToGuide }}
            </button>
        </div>
    </div>

    <!-- GLOBAL FLOATING DTS GUIDE BUTTON -->
    <button
        type="button"
        class="dts-accessibility-ui fixed bottom-5 right-5 z-[9998] flex h-[58px] w-[58px] items-center justify-center overflow-hidden rounded-full border-2 border-blue-200 bg-white p-1.5 shadow-[0_10px_28px_rgba(37,99,235,0.22)] transition duration-200 hover:-translate-y-0.5 hover:scale-105 hover:border-blue-300 hover:shadow-[0_14px_34px_rgba(37,99,235,0.28)] focus:outline-none focus:ring-4 focus:ring-blue-100 sm:bottom-6 sm:right-6"
        :aria-expanded="guideOpen"
        :aria-label="guideText.openGuide"
        :title="guideText.openGuide"
        @click="toggleGuidePanel"
    >
        <img
            src="/images/blue_book_reading_icon_cropped.png"
            alt=""
            aria-hidden="true"
            class="h-full w-full rounded-full object-contain"
        />
    </button>

</template>

<style>
.dts-accessibility-root {
    zoom: 1 !important;
    transform: none;
    -webkit-text-size-adjust: 100%;
    text-size-adjust: 100%;
}
</style>
