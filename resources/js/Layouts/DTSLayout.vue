<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'


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
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-800">
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
                                <!-- <span>Inventory</span> -->
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
</template>