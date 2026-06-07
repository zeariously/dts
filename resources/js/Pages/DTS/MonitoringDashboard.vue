<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({}),
    },
    transactions: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },
    peopleNoAction: {
        type: Array,
        default: () => [],
    },
    years: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const page = usePage()
const showUserMenu = ref(false)
const showNotificationModal = ref(false)
const showPendingModal = ref(false)

const authUser = computed(() => {
    return page.props.auth?.user || {}
})

const userDisplayName = computed(() => {
    return authUser.value.loginname
        || authUser.value.username
        || authUser.value.name
        || 'User'
})

const userInitial = computed(() => {
    return String(userDisplayName.value || 'U').slice(0, 1).toUpperCase()
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

const notificationCount = computed(() => {
    return notificationItems.value.length
})

const openNotificationModal = () => {
    showNotificationModal.value = true
}

const closeNotificationModal = () => {
    showNotificationModal.value = false
}

const openPendingModal = () => {
    showPendingModal.value = true
}

const closePendingModal = () => {
    showPendingModal.value = false
}

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const perPage = ref(props.filters.per_page || 15)
const selectedYear = ref(props.filters.year || '')
const expandedPeople = ref({})

let searchTimer = null
let skipNextSearchWatch = false

const transactionRows = computed(() => {
    return props.transactions?.data || []
})

const totalPendingDocuments = computed(() => {
    return props.peopleNoAction.reduce((total, person) => {
        return total + Number((person.documents || []).length)
    }, 0)
})

const criticalPersonnelCount = computed(() => {
    return props.peopleNoAction.filter((person) => Number(person.max_days_pending || 0) >= 15).length
})

const highestDaysPending = computed(() => {
    if (!props.peopleNoAction.length) {
        return 0
    }

    return Math.max(...props.peopleNoAction.map((person) => Number(person.max_days_pending || 0)))
})

const topPendingPeople = computed(() => {
    return [...props.peopleNoAction]
        .sort((a, b) => Number(b.max_days_pending || 0) - Number(a.max_days_pending || 0))
        .slice(0, 5)
})

const statusOptions = [
    {
        label: 'Overview',
        value: '',
        shortLabel: 'All',
    },
    {
        label: 'No Action Yet',
        value: 'no-action',
        shortLabel: 'No Action',
    },
    {
        label: 'Received',
        value: 'received',
        shortLabel: 'Received',
    },
    {
        label: 'Returned',
        value: 'returned',
        shortLabel: 'Returned',
    },
    {
        label: 'Pulled Out',
        value: 'pulled-out',
        shortLabel: 'Pulled Out',
    },
]

const activeStatusLabel = computed(() => {
    return statusOptions.find((item) => item.value === status.value)?.label || 'Overview'
})

const transactionSectionTitle = computed(() => {
    if (status.value === '') {
        return 'All Document Transactions'
    }

    return `${activeStatusLabel.value} Document Transactions`
})

const personKey = (person) => {
    return person.personnel_id ? String(person.personnel_id) : 'unassigned'
}

const togglePerson = (person) => {
    const key = personKey(person)

    expandedPeople.value = {
        ...expandedPeople.value,
        [key]: !expandedPeople.value[key],
    }
}

const isPersonExpanded = (person) => {
    return !!expandedPeople.value[personKey(person)]
}

const submitFilters = () => {
    router.get(
        '/dts/monitoring-dashboard',
        {
            search: search.value,
            status: status.value,
            per_page: perPage.value,
            year: selectedYear.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const resetFilters = () => {
    skipNextSearchWatch = true

    search.value = ''
    status.value = ''
    perPage.value = 15
    selectedYear.value = ''

    router.get(
        '/dts/monitoring-dashboard',
        {},
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

watch(search, () => {
    if (skipNextSearchWatch) {
        skipNextSearchWatch = false
        clearTimeout(searchTimer)
        return
    }

    clearTimeout(searchTimer)

    searchTimer = setTimeout(() => {
        submitFilters()
    }, 500)
})

onBeforeUnmount(() => {
    clearTimeout(searchTimer)
})

const setStatus = (value) => {
    status.value = value
    submitFilters()
}

const goBackToDts = () => {
    router.visit('/dts')
}

const goToDocument = (docId) => {
    router.visit(`/dts/${docId}`)
}

const goToPage = (url) => {
    if (!url) {
        return
    }

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    })
}

const formatDate = (value) => {
    if (!value) {
        return '-'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: 'numeric',
        minute: '2-digit',
    })
}

const cleanPaginationLabel = (label) => {
    return String(label || '')
        .replace('&laquo;', 'Previous')
        .replace('&raquo;', 'Next')
}

const daysPendingClass = (days) => {
    const numberOfDays = Number(days || 0)

    if (numberOfDays >= 15) {
        return 'border-red-200 bg-red-100 text-rose-800'
    }

    if (numberOfDays >= 7) {
        return 'border-amber-200 bg-amber-100 text-purple-800'
    }

    if (numberOfDays > 0) {
        return 'border-blue-200 bg-blue-100 text-blue-800'
    }

    return 'border-blue-100 bg-[#f5f7fb] text-slate-700'
}
</script>

<template>
    <div class="min-h-screen bg-[#f4f8ff]">
        <!-- Top Bar -->
        <header class="sticky top-0 z-30 border-b border-blue-100 bg-white/90 px-4 py-3 shadow-sm backdrop-blur-xl sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-[1700px] flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-[1.3rem] bg-white p-1 shadow-lg shadow-blue-200">
                        <img
                            src="/images/logo_dts-nobg.png"
                            alt="DTS Logo"
                            class="h-full w-full object-contain"
                        />
                    </div>

                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.22em] text-blue-600">
                            Monitoring Workspace
                        </p>

                        <h1 class="text-xl font-black text-slate-900">
                            Document Tracking Dashboard
                        </h1>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-2xl border border-blue-100 bg-white px-4 py-2.5 text-sm font-black text-slate-700 shadow-sm transition hover:bg-blue-50"
                        @click="goBackToDts"
                    >
                        <span>←</span>
                        <span>Back to DTS</span>
                    </button>

                    <button
                        type="button"
                        class="relative inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-700 shadow-sm ring-1 ring-blue-100 transition hover:bg-blue-100"
                        title="Notifications"
                        @click="openNotificationModal"
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
                            v-if="notificationCount > 0"
                            class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-rose-600 px-1 text-[10px] font-black leading-none text-white"
                        >
                            {{ notificationCount > 99 ? '99+' : notificationCount }}
                        </span>
                    </button>

                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-3 rounded-2xl bg-blue-600 px-4 py-2.5 text-left text-white shadow-lg shadow-blue-200 transition hover:bg-blue-700"
                            @click="showUserMenu = !showUserMenu"
                        >
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white text-sm font-black text-blue-700">
                                {{ userInitial }}
                            </span>

                            <span>
                                <span class="block text-xs font-semibold text-blue-100">
                                    Welcome back
                                </span>

                                <span class="block text-sm font-black">
                                    {{ userDisplayName }}
                                </span>
                            </span>

                            <span class="text-sm">
                                {{ showUserMenu ? '⌃' : '⌄' }}
                            </span>
                        </button>

                        <div
                            v-if="showUserMenu"
                            class="absolute right-0 mt-3 w-60 overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-2xl"
                        >
                            <div class="border-b border-blue-50 bg-blue-50 px-4 py-4">
                                <p class="text-xs font-black uppercase tracking-widest text-blue-500">
                                    Account
                                </p>

                                <p class="mt-1 text-sm font-black text-slate-900">
                                    {{ userDisplayName }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-bold text-slate-700 transition hover:bg-blue-50"
                                @click="router.visit('/profile')"
                            >
                                <span>👤</span>
                                <span>Profile</span>
                            </button>

                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-bold text-rose-600 transition hover:bg-rose-50"
                                @click="router.post('/logout')"
                            >
                                <span>🚪</span>
                                <span>Logout</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto grid max-w-[1700px] grid-cols-1 gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[360px_1fr] lg:px-8">
            <!-- Left Summary Panel -->
            <aside class="space-y-5">
                <!-- Pending Queue -->
                <section class="overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-blue-100">
                    <div class="bg-blue-600 px-5 py-5 text-white">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-100">
                                    Pending Queue
                                </p>

                                <h3 class="mt-1 text-xl font-black">
                                    People needing action
                                </h3>
                            </div>

                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 text-2xl ring-1 ring-white/20">
                                👥
                            </div>
                        </div>

                        <p class="mt-3 text-xs font-semibold leading-5 text-blue-100">
                            Quick preview of personnel with the longest pending documents.
                        </p>
                    </div>

                    <div class="p-5">
                        <div
                            v-if="topPendingPeople.length"
                            class="space-y-3"
                        >
                            <button
                                v-for="person in topPendingPeople"
                                :key="`queue-${person.personnel_id || 'unassigned'}-${person.personnel_name}`"
                                type="button"
                                class="w-full rounded-2xl border border-blue-100 bg-blue-50/60 p-4 text-left transition hover:border-blue-200 hover:bg-blue-50"
                                @click="openPendingModal"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ person.personnel_name || 'Unassigned' }}
                                        </p>

                                        <p class="mt-1 text-xs font-semibold text-slate-500">
                                            {{ person.pending_transactions ?? 0 }} pending transaction(s)
                                        </p>
                                    </div>

                                    <span
                                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-black"
                                        :class="daysPendingClass(person.max_days_pending)"
                                    >
                                        {{ person.max_days_pending ?? 0 }} day(s)
                                    </span>
                                </div>
                            </button>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl bg-emerald-50 px-5 py-8 text-center ring-1 ring-emerald-100"
                        >
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-2xl shadow-sm">
                                ✅
                            </div>

                            <h3 class="mt-4 text-base font-black text-emerald-800">
                                No pending action
                            </h3>

                            <p class="mt-2 text-xs font-semibold leading-5 text-emerald-700">
                                Everything looks clear for now.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="mt-5 w-full rounded-2xl bg-blue-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700"
                            @click="openPendingModal"
                        >
                            Open Full Pending Monitoring
                        </button>
                    </div>
                </section>
            </aside>

            <!-- Main Workspace -->
            <section class="space-y-5">
                <!-- Stats Cards -->
                <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <button
                        type="button"
                        class="rounded-[2rem] bg-white p-5 text-left shadow-sm ring-1 ring-blue-100 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-blue-100"
                        @click="setStatus('')"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-2xl">
                                📊
                            </div>

                            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">
                                All
                            </span>
                        </div>

                        <p class="mt-5 text-3xl font-black text-slate-900">
                            {{ stats.total_documents ?? 0 }}
                        </p>

                        <p class="mt-1 text-sm font-bold text-slate-500">
                            Total Documents
                        </p>
                    </button>

                    <button
                        type="button"
                        class="rounded-[2rem] bg-white p-5 text-left shadow-sm ring-1 ring-blue-100 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-blue-100"
                        @click="setStatus('no-action')"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-2xl">
                                ⏳
                            </div>

                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700">
                                Pending
                            </span>
                        </div>

                        <p class="mt-5 text-3xl font-black text-slate-900">
                            {{ stats.no_action ?? 0 }}
                        </p>

                        <p class="mt-1 text-sm font-bold text-slate-500">
                            No Action Yet
                        </p>
                    </button>

                    <button
                        type="button"
                        class="rounded-[2rem] bg-white p-5 text-left shadow-sm ring-1 ring-blue-100 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-blue-100"
                        @click="setStatus('received')"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-2xl">
                                ✅
                            </div>

                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                Done
                            </span>
                        </div>

                        <p class="mt-5 text-3xl font-black text-slate-900">
                            {{ stats.received ?? 0 }}
                        </p>

                        <p class="mt-1 text-sm font-bold text-slate-500">
                            Received
                        </p>
                    </button>

                    <button
                        type="button"
                        class="rounded-[2rem] bg-white p-5 text-left shadow-sm ring-1 ring-blue-100 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-blue-100"
                        @click="setStatus('returned')"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-50 text-2xl">
                                ↩️
                            </div>

                            <span class="rounded-full bg-purple-50 px-3 py-1 text-xs font-black text-purple-700">
                                Return
                            </span>
                        </div>

                        <p class="mt-5 text-3xl font-black text-slate-900">
                            {{ stats.returned ?? 0 }}
                        </p>

                        <p class="mt-1 text-sm font-bold text-slate-500">
                            Returned
                        </p>
                    </button>
                </section>

                <!-- Filters -->
                <section class="rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-blue-100">
                    <div class="mb-5 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-600">
                                Search Filters
                            </p>

                            <h2 class="mt-1 text-lg font-black text-slate-900">
                                Find a document transaction
                            </h2>
                        </div>

                        <div class="rounded-full bg-blue-50 px-4 py-2 text-sm font-black text-blue-700">
                            Current View: {{ activeStatusLabel }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
                        <div class="lg:col-span-6">
                            <label class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">
                                Search
                            </label>

                            <div class="relative">
                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search Doc ID, subject, or assigned personnel..."
                                    class="w-full rounded-2xl border border-blue-100 bg-blue-50/60 px-5 py-3.5 pl-11 text-sm font-semibold text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                                />

                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                                    🔍
                                </span>
                            </div>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">
                                Year
                            </label>

                            <select
                                v-model="selectedYear"
                                class="w-full rounded-2xl border border-blue-100 bg-blue-50/60 px-4 py-3.5 text-sm font-bold text-slate-800 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                                @change="submitFilters"
                            >
                                <option value="">All Years</option>

                                <option
                                    v-for="year in years"
                                    :key="year"
                                    :value="year"
                                >
                                    {{ year }}
                                </option>
                            </select>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">
                                Entries
                            </label>

                            <select
                                v-model="perPage"
                                class="w-full rounded-2xl border border-blue-100 bg-blue-50/60 px-4 py-3.5 text-sm font-bold text-slate-800 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100"
                                @change="submitFilters"
                            >
                                <option :value="10">10</option>
                                <option :value="15">15</option>
                                <option :value="25">25</option>
                                <option :value="50">50</option>
                                <option :value="100">100</option>
                            </select>
                        </div>

                        <div class="flex items-end gap-2 lg:col-span-2">
                            <button
                                type="button"
                                class="w-full rounded-2xl bg-blue-600 px-4 py-3.5 text-sm font-black text-white shadow-sm transition hover:bg-blue-700"
                                @click="submitFilters"
                            >
                                Apply
                            </button>

                            <button
                                type="button"
                                class="w-full rounded-2xl border border-blue-100 bg-white px-4 py-3.5 text-sm font-black text-slate-600 transition hover:bg-slate-50"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Transaction List -->
                <section class="overflow-hidden rounded-[2rem] bg-white shadow-xl shadow-blue-100/70 ring-1 ring-blue-100">
                    <div class="flex flex-col gap-4 border-b border-blue-50 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-600">
                                Transaction List
                            </p>

                            <h2 class="mt-1 text-2xl font-black text-slate-900">
                                {{ transactionSectionTitle }}
                            </h2>

                            <p class="mt-1 text-sm font-semibold text-slate-500">
                                Click view details to open a specific document record.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-blue-50 px-4 py-2 text-sm font-black text-blue-700 ring-1 ring-blue-100">
                                {{ transactionRows.length }} shown
                            </span>

                            <span class="rounded-full bg-slate-50 px-4 py-2 text-sm font-black text-slate-600 ring-1 ring-slate-200">
                                {{ activeStatusLabel }}
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-blue-50 bg-blue-50/70 text-left text-xs font-black uppercase tracking-[0.18em] text-slate-500">
                                    <th class="whitespace-nowrap px-5 py-4">
                                        Document No.
                                    </th>

                                    <th class="min-w-[420px] px-5 py-4">
                                        Subject
                                    </th>

                                    <th class="whitespace-nowrap px-5 py-4">
                                        Assigned Personnel
                                    </th>

                                    <th class="whitespace-nowrap px-5 py-4 text-center">
                                        Days Pending
                                    </th>

                                    <th class="whitespace-nowrap px-5 py-4 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="transaction in transactionRows"
                                    :key="transaction.IDdist"
                                    class="border-b border-slate-100 transition hover:bg-blue-50/60"
                                >
                                    <td class="whitespace-nowrap px-5 py-4 align-top">
                                        <div class="flex items-center gap-3">
                                           

                                            <div>
                                                <p class="text-lg font-black text-blue-700">
                                                    {{ transaction.IDdoc }}
                                                </p>
<!-- 
                                                <p class="mt-1 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">
                                                    IDdist: {{ transaction.IDdist || '-' }}
                                                </p> -->
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 align-top">
                                        <p class="text-sm font-black leading-snug text-slate-900">
                                            {{ transaction.subject || 'No subject' }}
                                        </p>
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4 align-top">
                                        <div class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-sm font-black text-slate-800 ring-1 ring-slate-200">
                                            <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                                            {{ transaction.assigned_personnel || 'Unassigned' }}
                                        </div>
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4 text-center align-top">
                                        <span
                                            class="inline-flex rounded-full border px-3 py-2 text-xs font-black"
                                            :class="daysPendingClass(transaction.days_pending)"
                                        >
                                            {{ transaction.days_pending ?? 0 }} day(s)
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4 text-right align-top">
                                        <button
                                            type="button"
                                            class="rounded-2xl bg-blue-600 px-5 py-2.5 text-xs font-black text-white shadow-md shadow-blue-100 transition hover:bg-blue-700"
                                            @click="goToDocument(transaction.IDdoc)"
                                        >
                                            View Details
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="transactionRows.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-6 py-16 text-center"
                                    >
                                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-blue-50 text-3xl">
                                            🔍
                                        </div>

                                        <h3 class="mt-4 text-lg font-black text-slate-800">
                                            No document transactions found.
                                        </h3>

                                        <p class="mt-2 text-sm font-semibold text-slate-500">
                                            Try adjusting your search, year, or entries filter.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="transactions.links && transactions.links.length > 0"
                        class="flex flex-col gap-3 border-t border-blue-100 bg-blue-50/50 px-5 py-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <p class="text-sm font-black text-slate-700">
                            Showing page
                            <span class="text-blue-700">
                                {{ transactions.current_page }}
                            </span>
                            of
                            <span class="text-blue-700">
                                {{ transactions.last_page }}
                            </span>
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(link, index) in transactions.links"
                                :key="`${link.label}-${index}`"
                                type="button"
                                class="rounded-xl border px-4 py-2 text-xs font-black transition"
                                :class="[
                                    link.active
                                        ? 'border-blue-600 bg-blue-600 text-white shadow-md'
                                        : 'border-blue-100 bg-white text-slate-700 hover:bg-blue-50',
                                    !link.url ? 'pointer-events-none opacity-50' : '',
                                ]"
                                :disabled="!link.url"
                                @click="goToPage(link.url)"
                            >
                                {{ cleanPaginationLabel(link.label) }}
                            </button>
                        </div>
                    </div>
                </section>
            </section>
        </main>

        <!-- Pending Monitoring Modal -->
        <div
            v-if="showPendingModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8 backdrop-blur-sm"
            @click.self="closePendingModal"
        >
            <div class="max-h-[92vh] w-full max-w-6xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                <div class="border-b border-blue-100 bg-blue-600 px-6 py-5 text-white">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                                Pending Monitoring
                            </p>

                            <h2 class="mt-2 text-2xl font-black">
                                Personnel with Pending Receive / Action
                            </h2>

                            <p class="mt-1 text-sm font-semibold text-blue-100">
                                Review pending documents by personnel.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-xl bg-white/15 px-4 py-2 text-sm font-black text-white transition hover:bg-white/25"
                            @click="closePendingModal"
                        >
                            Close
                        </button>
                    </div>
                </div>

                <div class="max-h-[72vh] overflow-y-auto bg-slate-50 p-5">
                    <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-blue-100">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">
                                Pending Personnel
                            </p>

                            <p class="mt-2 text-3xl font-black text-blue-800">
                                {{ peopleNoAction.length }}
                            </p>
                        </div>

                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-sky-100">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-sky-600">
                                Pending Documents
                            </p>

                            <p class="mt-2 text-3xl font-black text-sky-800">
                                {{ totalPendingDocuments }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="person in peopleNoAction"
                            :key="`${person.personnel_id || 'unassigned'}-${person.personnel_name}`"
                            class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm"
                        >
                            <button
                                type="button"
                                class="flex w-full flex-col gap-3 p-4 text-left transition hover:bg-blue-50 md:flex-row md:items-center md:justify-between"
                                @click="togglePerson(person)"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-lg font-black transition"
                                        :class="isPersonExpanded(person) ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-600'"
                                    >
                                        {{ isPersonExpanded(person) ? '−' : '+' }}
                                    </div>

                                    <div>
                                        <p class="text-base font-black text-slate-900">
                                            {{ person.personnel_name || 'Unassigned' }}
                                        </p>

                                        <p class="mt-1 text-xs font-semibold text-slate-500">
                                            Click to view assigned pending documents.
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2 md:min-w-[320px]">
                                    <div class="rounded-2xl bg-blue-50 px-4 py-3 text-center ring-1 ring-blue-100">
                                        <p class="text-[11px] font-black uppercase tracking-wide text-blue-500">
                                            Pending
                                        </p>

                                        <p class="mt-1 text-xl font-black text-blue-800">
                                            {{ person.pending_transactions ?? 0 }}
                                        </p>
                                    </div>

                                    <div class="rounded-2xl bg-slate-50 px-4 py-3 text-center ring-1 ring-slate-200">
                                        <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                                            Max Days
                                        </p>

                                        <p class="mt-1 text-xl font-black text-slate-800">
                                            {{ person.max_days_pending ?? 0 }}
                                        </p>
                                    </div>
                                </div>
                            </button>

                            <div
                                v-if="isPersonExpanded(person)"
                                class="border-t border-slate-100 bg-blue-50/50 p-4"
                            >
                                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-700">
                                        Pending Documents of {{ person.personnel_name || 'Unassigned' }}
                                    </p>

                                    <p class="w-fit rounded-full bg-white px-3 py-2 text-xs font-black text-slate-600 shadow-sm">
                                        {{ (person.documents || []).length }} document(s)
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                                    <article
                                        v-for="doc in person.documents || []"
                                        :key="`${person.personnel_id || 'unassigned'}-${doc.IDdoc}`"
                                        class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"
                                    >
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">
                                                    Document ID
                                                </p>

                                                <p class="mt-1 text-xl font-black text-blue-700">
                                                    #{{ doc.IDdoc }}
                                                </p>
                                            </div>

                                            <span
                                                class="w-fit rounded-full border px-3 py-2 text-xs font-black"
                                                :class="daysPendingClass(doc.days_pending)"
                                            >
                                                {{ doc.days_pending ?? 0 }} day(s)
                                            </span>
                                        </div>

                                        <p class="mt-4 text-sm font-black leading-snug text-slate-900">
                                            {{ doc.subject || 'No subject' }}
                                        </p>

                                        <p class="mt-3 text-xs font-semibold text-slate-600">
                                            Date Sent: {{ formatDate(doc.distdate) }}
                                        </p>

                                        <button
                                            type="button"
                                            class="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-blue-700"
                                            @click.stop="goToDocument(doc.IDdoc)"
                                        >
                                            View Details
                                        </button>
                                    </article>

                                    <div
                                        v-if="!person.documents || person.documents.length === 0"
                                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"
                                    >
                                        <p class="text-3xl">
                                            📭
                                        </p>

                                        <p class="mt-3 text-base font-black text-slate-800">
                                            No pending documents found.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="peopleNoAction.length === 0"
                            class="rounded-2xl bg-emerald-50 p-10 text-center ring-1 ring-emerald-100"
                        >
                            <p class="text-4xl">
                                ✅
                            </p>

                            <h3 class="mt-4 text-lg font-black text-emerald-800">
                                No personnel with pending action.
                            </h3>

                            <p class="mt-2 text-sm font-semibold text-emerald-700">
                                Everything looks clear for the selected filter.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Modal -->
        <div
            v-if="showNotificationModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 px-4 py-8 backdrop-blur-sm"
            @click.self="closeNotificationModal"
        >
            <div class="max-h-[90vh] w-full max-w-3xl overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                <div class="border-b border-blue-100 bg-blue-600 px-6 py-5 text-white">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                                Notifications
                            </p>

                            <h2 class="mt-2 text-xl font-black">
                                Document Notifications
                            </h2>

                            <p class="mt-1 text-sm font-semibold text-blue-100">
                                Latest document alerts from your DTS dashboard.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-xl bg-white/15 px-4 py-2 text-sm font-black text-white transition hover:bg-white/25"
                            @click="closeNotificationModal"
                        >
                            Close
                        </button>
                    </div>
                </div>

                <div class="bg-slate-50 p-6">
                    <div
                        v-if="notificationItems.length"
                        class="max-h-[55vh] space-y-3 overflow-y-auto pr-1"
                    >
                        <div
                            v-for="(item, index) in notificationItems"
                            :key="`monitoring-notification-${item.IDdoc || item.document_no || index}`"
                            class="rounded-2xl border border-blue-100 bg-white p-4 shadow-sm"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">
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
                                            class="rounded-full bg-rose-600 px-3 py-1 text-xs font-black text-white"
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

                                    <p class="mt-3 break-words text-sm font-black text-slate-900">
                                        {{ item.subject || 'No subject' }}
                                    </p>

                                    <p class="mt-2 text-xs font-semibold text-slate-600">
                                        {{ item.transferred_to || item.received_office || item.from_office || '-' }}
                                    </p>
                                </div>

                                <button
                                    v-if="item.IDdoc"
                                    type="button"
                                    class="shrink-0 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white transition hover:bg-blue-700"
                                    @click="goToDocument(item.IDdoc)"
                                >
                                    View Details
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"
                    >
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-2xl shadow-sm">
                            🔔
                        </div>

                        <h3 class="mt-4 text-lg font-black text-slate-900">
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
</template>