<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'

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
const seenNotificationKeys = ref([])

const authUser = computed(() => page.props.auth?.user || {})

const userDisplayName = computed(() => {
    return authUser.value?.loginname
        || authUser.value?.username
        || authUser.value?.name
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

onMounted(() => {
    loadSeenNotificationKeys()
})

const notificationItems = computed(() => {
    const viewerNotifications = page.props.viewerNotifications || []
    const creatorReceivedNotifications = page.props.creatorReceivedNotifications || []
    const notifications = page.props.notifications || []

    return [
        ...viewerNotifications,
        ...creatorReceivedNotifications,
        ...notifications,
    ]
})

const visibleNotificationItems = computed(() => {
    return notificationItems.value.filter((item) => {
        return !seenNotificationKeys.value.includes(notificationKey(item))
    })
})

const displayNotificationCount = computed(() => {
    return visibleNotificationItems.value.length
})

const openNotifications = () => {
    showNotifications.value = true
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
    'collab-received',
    'for-action',
    'returned',
]

const isLibraryActive = computed(() => {
    return page.url.startsWith('/dts/library')
})

const isReportsActive = computed(() => {
    return activeSection.value === 'reports'
})

const isAboutActive = computed(() => {
    return activeSection.value === 'about'
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
        && !isReportsActive.value
        && !isAboutActive.value
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
            <!-- SIDE NAV BAR -->
            <aside class="fixed inset-y-0 left-0 z-30 flex w-80 flex-col border-r border-slate-200 bg-slate-950 text-slate-200">
                <!-- Logo / Title -->
                <div class="border-b border-slate-800 px-6 py-6">
                    <div class="flex items-center gap-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white p-2 shadow-sm">
                            <img
                                src="/images/logo_dts-nobg.png"
                                alt="Pantalan Logo"
                                class="h-14 w-14 object-contain"
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
                    </div>
                </div>

                <!-- Menu -->
                <nav class="flex-1 overflow-y-auto px-4 py-5">
                    <div class="space-y-3">
                        <Link
                            href="/dts"
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
                            href="/dts?section=incoming"
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

                        <Link
                            href="/dts?section=sent-docs"
                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"
                            :class="navLinkClass(isOutgoingActive)"
                        >
                            <span>Outgoing</span>

                            <span
                                v-if="isOutgoingActive"
                                class="text-xs font-bold"
                            >
                            </span>
                        </Link>

                        <Link
                            href="/dts/library"
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
                            href="/dts?section=about"
                            class="flex items-center justify-between rounded-xl px-4 py-3 text-sm font-bold transition"
                            :class="navLinkClass(isAboutActive)"
                        >
                            <span>About</span>

                            <span
                                v-if="isAboutActive"
                                class="text-xs font-bold"
                            >
                            </span>
                        </Link>
                    </div>
                </nav>
            </aside>

            <!-- RIGHT CONTENT -->
            <div class="min-w-0 flex-1 pl-80">
                <!-- TOP USER BAR -->
                <header class="sticky top-0 z-20 border-b border-slate-200 bg-white px-6 py-4 shadow-sm">
                    <div class="flex items-center justify-end gap-3">
                        <!-- Notification Bell -->
                        <button
                            type="button"
                            class="relative inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-700 text-white shadow-sm transition hover:bg-indigo-800 focus:outline-none focus:ring-4 focus:ring-indigo-100"
                            title="Notifications"
                            @click="openNotifications"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                class="h-5 w-5"
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
                                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-red-600 px-1 text-[10px] font-black leading-none text-white"
                            >
                                {{ displayNotificationCount > 99 ? '99+' : displayNotificationCount }}
                            </span>
                        </button>

                        <div class="relative">
                            <button
                                type="button"
                                class="flex items-center gap-3 rounded-2xl bg-blue-600 px-4 py-2.5 text-left text-white shadow-sm hover:bg-blue-700"
                                @click="showUserMenu = !showUserMenu"
                            >
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-white text-sm font-bold text-blue-700">
                                    {{ userInitial }}
                                </span>

                                <span>
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
                                class="absolute right-0 mt-3 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
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
                </header>

                <slot />

                <!-- NOTIFICATION MODAL -->
                <div
                    v-if="showNotifications"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8"
                    @click.self="closeNotifications"
                >
                    <div class="max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                        <div class="border-b border-blue-100 bg-blue-600 px-6 py-5 text-white">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                                        Notifications
                                    </p>

                                    <h2 class="mt-2 text-2xl font-black">
                                        Document Notifications
                                    </h2>

                                    <p class="mt-1 text-sm font-semibold text-blue-100">
                                        Latest document alerts from your DTS dashboard.
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

                        <div class="p-6">
                            <div
                                v-if="visibleNotificationItems.length"
                                class="max-h-[55vh] space-y-3 overflow-y-auto pr-1"
                            >
                                <div
                                    v-for="(item, index) in visibleNotificationItems"
                                    :key="`layout-notification-${item.IDdoc || item.document_no || index}`"
                                    class="rounded-2xl border border-blue-100 bg-blue-50 p-4"
                                >
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-blue-700">
                                                    Doc ID: {{ item.document_no || item.IDdoc || '-' }}
                                                </span>

                                                <span
                                                    v-if="item.notification_type === 'received_by_addressee'"
                                                    class="rounded-full bg-emerald-600 px-3 py-1 text-xs font-black text-white"
                                                >
                                                    Received
                                                </span>

                                                <span
                                                    v-else-if="item.is_overdue"
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
                                                {{ item.subject || 'No subject' }}
                                            </p>

                                            <p class="mt-2 text-sm font-semibold text-slate-600">
                                                {{ item.transferred_to || item.received_office || item.from_office || '-' }}
                                            </p>
                                        </div>

                                        <Link
                                            v-if="item.IDdoc"
                                            :href="`/dts/${item.IDdoc}`"
                                            class="shrink-0 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-black text-white hover:bg-blue-700"
                                            @click="markNotificationSeen(item); closeNotifications()"
                                        >
                                            View Details
                                        </Link>
                                    </div>
                                </div>
                            </div>

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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>