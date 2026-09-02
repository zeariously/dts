<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import DTSLayout from '@/Layouts/DTSLayout.vue'

defineOptions({
    layout: DTSLayout,
})

const props = defineProps({
    inventoryItems: {
        type: Array,
        default: () => [],
    },
})

const activeTab = ref('supplies')
const search = ref('')
const yearFilter = ref(2026)
const unitFilter = ref('all')
const quarterFilter = ref('all')
const currentPage = ref(1)

const perPage = 8

const showHistoryModal = ref(false)
const historyItem = ref(null)
const inventoryHistories = ref([])
const historyLoading = ref(false)
const historyError = ref('')

const showReleaseItemModal = ref(false)
const releasingItem = ref(null)
const releaseItemErrors = ref({})

const releaseItemForm = ref({
    releaseQuantity: '',
    remarks: '',
})

const showFullEditModal = ref(false)
const fullEditingItem = ref(null)
const fullEditErrors = ref({})
const fullEditForm = ref({
    category: 'supplies',
    item: '',
    unit: '',
    inventory_year: 2026,
    fixed_value: '',
    currently_available: '',
    quarters: [],
    remarks: '',
})

const showAddItemModal = ref(false)
const addItemErrors = ref({})

const newItemForm = ref({
    item: '',
    unit: '',
    inventory_year: 2026,
    fixed: '',
    currently_available: '',
    quarters: [],
    remarks: '',
})

const quarterOptions = [
    { value: 'all', label: 'All Quarters' },
    { value: 'q1', label: 'Q1' },
    { value: 'q2', label: 'Q2' },
    { value: 'q3', label: 'Q3' },
    { value: 'q4', label: 'Q4' },
]

const quarterValues = [
    'q1',
    'q2',
    'q3',
    'q4',
]


const yearOptions = computed(() => {
    const currentYear =
        new Date().getFullYear()

    const dataYears =
        inventoryItemsState.value
            .map((item) =>
                Number(item.inventory_year)
            )
            .filter((year) =>
                Number.isInteger(year)
                && year >= 2026
            )

    const maxYear =
        Math.max(
            currentYear + 5,
            2030,
            ...(dataYears.length
                ? dataYears
                : [2026])
        )

    return Array.from(
        {
            length:
                maxYear - 2026 + 1,
        },
        (_, index) =>
            2026 + index
    )
})

/*
|--------------------------------------------------------------------------
| DATABASE DATA
|--------------------------------------------------------------------------
*/

const normalizeQuarters = (value) => {
    if (Array.isArray(value)) {
        return value.filter((quarter) =>
            quarterValues.includes(quarter)
        )
    }

    if (typeof value === 'string' && value.trim()) {
        try {
            const parsed = JSON.parse(value)

            return Array.isArray(parsed)
                ? parsed.filter((quarter) =>
                    quarterValues.includes(quarter)
                )
                : []
        } catch (error) {
            return []
        }
    }

    return []
}

const normalizeInventoryItem = (item) => {
    const fixed =
        item.fixed_value !== null
        && item.fixed_value !== undefined
        && String(item.fixed_value).trim() !== ''
            ? Number(item.fixed_value)
            : (
                item.fixed !== null
                && item.fixed !== undefined
                && String(item.fixed).trim() !== ''
                    ? Number(item.fixed)
                    : null
            )

    const currentlyAvailable =
        item.currently_available !== null
        && item.currently_available !== undefined
        && String(item.currently_available).trim() !== ''
            ? Number(item.currently_available)
            : null

    const generatedReleased =
        item.total_released !== null
        && item.total_released !== undefined
        && String(item.total_released).trim() !== ''
            ? Number(item.total_released)
            : (
                Number.isFinite(fixed)
                && fixed > 0
                && Number.isFinite(currentlyAvailable)
                    ? Math.max(
                        0,
                        fixed - currentlyAvailable
                    )
                    : null
            )

    const trackedReleased =
        item.tracked_released !== null
        && item.tracked_released !== undefined
        && String(item.tracked_released).trim() !== ''
            ? Number(item.tracked_released)
            : 0

    return {
        ...item,
        category: String(item.category ?? '')
            .trim()
            .toLowerCase(),
        unit: String(item.unit ?? '')
            .trim()
            .toUpperCase(),
        fixed,
        currently_available:
            Number.isFinite(currentlyAvailable)
                ? currentlyAvailable
                : null,
        total_released:
            Number.isFinite(generatedReleased)
                ? generatedReleased
                : null,
        tracked_released:
            Number.isFinite(trackedReleased)
                ? Math.max(0, trackedReleased)
                : 0,
        inventory_year:
            Number.isInteger(
                Number(item.inventory_year)
            )
                ? Number(item.inventory_year)
                : 2026,
        quarters:
            normalizeQuarters(item.quarters),
        remarks:
            item.remarks ?? '',
    }
}

/*
|--------------------------------------------------------------------------
| LOCAL INVENTORY STATE
|--------------------------------------------------------------------------
|
| Keep a local mirror of the Inertia prop.
|
| Why:
| - After a successful release, update the row immediately.
| - The remaining bar and Quantity Released move immediately.
| - Then router.reload() re-syncs the authoritative DB values.
|
*/
const inventoryItemsState = ref([])

watch(
    () => props.inventoryItems,
    (items) => {
        inventoryItemsState.value =
            Array.isArray(items)
                ? items.map((item) => ({ ...item }))
                : []
    },
    {
        immediate: true,
        deep: true,
    }
)

const updateLocalInventoryItem = (
    inventoryItemId,
    updates
) => {
    inventoryItemsState.value =
        inventoryItemsState.value.map((item) => {
            if (
                Number(item.id)
                !== Number(inventoryItemId)
            ) {
                return item
            }

            return {
                ...item,
                ...updates,
            }
        })
}

const normalizedInventoryItems = computed(() =>
    inventoryItemsState.value.map(
        normalizeInventoryItem
    )
)

const suppliesItems = computed(() =>
    normalizedInventoryItems.value.filter(
        (item) => item.category === 'supplies'
    )
)

const ictItems = computed(() =>
    normalizedInventoryItems.value.filter(
        (item) => item.category === 'ict'
    )
)

/*
|--------------------------------------------------------------------------
| CURRENT DATA
|--------------------------------------------------------------------------
*/

const currentItems = computed(() => {
    return activeTab.value === 'supplies'
        ? suppliesItems.value
        : ictItems.value
})

const currentTitle = computed(() => {
    return activeTab.value === 'supplies'
        ? 'Supplies Inventory'
        : 'ICT & Other Items'
})

const currentSubtitle = computed(() => {
    return ''
})

const suppliesUnitOptions = [
    { value: 'REAM', label: 'Ream' },
    { value: 'BOX', label: 'Box' },
    { value: 'ROLL', label: 'Roll' },
    { value: 'PIECE', label: 'Piece' },
    { value: 'PACK', label: 'Pack' },
    { value: 'UNIT', label: 'Unit' },
    { value: 'TUBE', label: 'Tube' },
    { value: 'PAD', label: 'Pad' },
    { value: 'BOOK', label: 'Book' },
    { value: 'SETS', label: 'Sets' },
    { value: 'BUNDLE', label: 'Bundle' },
]

const ictUnitOptions = [
    { value: 'UNIT', label: 'Unit' },
    { value: 'LOT', label: 'Lot' },
    { value: 'PAX', label: 'Pax' },
]

const unitOptions = computed(() => {
    return activeTab.value === 'supplies'
        ? suppliesUnitOptions
        : ictUnitOptions
})

/*
|--------------------------------------------------------------------------
| QUARTER LOGIC
|--------------------------------------------------------------------------
*/

const allNewItemQuartersSelected = computed(() => {
    return quarterValues.every((quarter) =>
        newItemForm.value.quarters.includes(quarter)
    )
})

const newItemQuantityReleased = computed(() => {
    const fixedRaw = newItemForm.value.fixed
    const fixedValue = Number(fixedRaw)

    const hasBaseline =
        fixedRaw !== ''
        && fixedRaw !== null
        && fixedRaw !== undefined
        && Number.isFinite(fixedValue)
        && fixedValue > 0

    /*
     * No usable Fixed Value:
     * Quantity Released starts at 0 and will accumulate
     * every release performed through this system.
     */
    if (!hasBaseline) {
        return 0
    }

    const currentRaw =
        newItemForm.value.currently_available

    const currentlyAvailable =
        Number(currentRaw)

    if (
        currentRaw === ''
        || currentRaw === null
        || currentRaw === undefined
        || !Number.isFinite(currentlyAvailable)
    ) {
        return null
    }

    return Math.max(
        0,
        fixedValue - currentlyAvailable
    )
})

const inferredItemQuarters = (item) => {
    return Array.isArray(item?.quarters)
        ? [
            ...new Set(
                item.quarters.filter(
                    (quarter) =>
                        quarterValues.includes(quarter)
                )
            ),
        ]
        : []
}

const quarterBadges = (item) => {
    const itemQuarters =
        inferredItemQuarters(item)

    if (quarterFilter.value !== 'all') {
        return itemQuarters.includes(
            quarterFilter.value
        )
            ? [
                quarterFilter.value
                    .toUpperCase(),
            ]
            : []
    }

    return itemQuarters.map(
        (quarter) =>
            quarter.toUpperCase()
    )
}

const itemMatchesQuarter = (
    item,
    selectedQuarter
) => {
    if (selectedQuarter === 'all') {
        return true
    }

    return inferredItemQuarters(item)
        .includes(selectedQuarter)
}

/*
|--------------------------------------------------------------------------
| ADD ITEM
|--------------------------------------------------------------------------
*/

const resetNewItemForm = () => {
    newItemForm.value = {
        item: '',
        unit: '',
        inventory_year:
            Number(yearFilter.value) || 2026,
        fixed: '',
        currently_available: '',
        quarters:
            quarterFilter.value === 'all'
                ? []
                : [quarterFilter.value],
        remarks: '',
    }

    addItemErrors.value = {}
}

const openAddItemModal = () => {
    resetNewItemForm()
    showAddItemModal.value = true
}

const closeAddItemModal = () => {
    showAddItemModal.value = false
    resetNewItemForm()
}

const toggleAllNewItemQuarters = (event) => {
    newItemForm.value.quarters =
        event.target.checked
            ? [...quarterValues]
            : []
}

const addNewItem = () => {
    addItemErrors.value = {}

    const itemName = String(
        newItemForm.value.item || ''
    ).trim()

    const unit = String(
        newItemForm.value.unit || ''
    )
        .trim()
        .toUpperCase()

    const inventoryYear =
        Number(
            newItemForm.value.inventory_year
        )

    const remarks = String(
        newItemForm.value.remarks ?? ''
    ).trim()

    const isSupplies =
        activeTab.value === 'supplies'

    const hasFixedValue =
        isSupplies
        && newItemForm.value.fixed !== ''
        && newItemForm.value.fixed !== null
        && newItemForm.value.fixed !== undefined

    const fixedValue = hasFixedValue
        ? Number(newItemForm.value.fixed)
        : null

    const currentlyAvailable = isSupplies
        ? Number(
            newItemForm.value.currently_available
        )
        : null

    /*
     * Quarter assignment now applies to BOTH tabs.
     */
    const quarters = [
        ...new Set(
            newItemForm.value.quarters
        ),
    ].filter((quarter) =>
        quarterValues.includes(quarter)
    )

    if (!itemName) {
        addItemErrors.value.item =
            'Item name is required.'
    }

    if (!unit) {
        addItemErrors.value.unit =
            'Unit of measure is required.'
    }

    if (
        !Number.isInteger(inventoryYear)
        || inventoryYear < 2026
    ) {
        addItemErrors.value.inventory_year =
            'Select a valid year from 2026 onwards.'
    }

    if (!quarters.length) {
        addItemErrors.value.quarters =
            'Select at least one applicable quarter.'
    }

    if (isSupplies) {
        if (
            hasFixedValue
            && (
                !Number.isFinite(fixedValue)
                || fixedValue < 0
            )
        ) {
            addItemErrors.value.fixed =
                'Enter a valid fixed value or leave it blank.'
        }

        if (
            newItemForm.value.currently_available === ''
            || newItemForm.value.currently_available === null
            || newItemForm.value.currently_available === undefined
        ) {
            addItemErrors.value.currently_available =
                'Currently available quantity is required.'
        } else if (
            !Number.isFinite(currentlyAvailable)
            || currentlyAvailable < 0
        ) {
            addItemErrors.value.currently_available =
                'Enter a valid currently available quantity.'
        }
    }

    /*
     * Same item/unit can exist again in another year,
     * but not twice inside the same year.
     */
    const duplicateExists =
        currentItems.value.some((item) =>
            Number(item.inventory_year)
                === inventoryYear
            &&
            String(item.item || '')
                .trim()
                .toLowerCase()
                === itemName.toLowerCase()
            &&
            String(item.unit || '')
                .trim()
                .toUpperCase()
                === unit
        )

    if (duplicateExists) {
        addItemErrors.value.item =
            `This item and unit already exist for ${inventoryYear}.`
    }

    if (
        Object.keys(
            addItemErrors.value
        ).length
    ) {
        return
    }

    router.post(
        '/dts/inventory',
        {
            category: activeTab.value,
            item: itemName,
            unit,
            inventory_year:
                inventoryYear,

            fixed_value:
                isSupplies
                    ? fixedValue
                    : null,

            currently_available:
                isSupplies
                    ? currentlyAvailable
                    : null,

            quarters,
            remarks,
        },
        {
            preserveScroll: true,

            onSuccess: () => {
                yearFilter.value =
                    inventoryYear

                search.value = ''
                unitFilter.value = 'all'
                quarterFilter.value = 'all'
                currentPage.value = 1
                closeAddItemModal()
            },

            onError: (errors) => {
                addItemErrors.value = {
                    ...errors,
                }
            },
        }
    )
}


/*
|--------------------------------------------------------------------------
| FILTERING
|--------------------------------------------------------------------------
*/

const filteredItems = computed(() => {
    const term =
        search.value
            .trim()
            .toLowerCase()

    return currentItems.value.filter((item) => {
        const matchesYear =
            Number(item.inventory_year)
            === Number(yearFilter.value)

        const matchesSearch =
            !term
            ||
            String(item.item || '')
                .toLowerCase()
                .includes(term)
            ||
            String(item.unit || '')
                .toLowerCase()
                .includes(term)
            ||
            String(item.remarks || '')
                .toLowerCase()
                .includes(term)

        const matchesUnit =
            unitFilter.value === 'all'
            ||
            item.unit === unitFilter.value

        const matchesQuarter =
            quarterFilter.value === 'all'
            ||
            inferredItemQuarters(item)
                .includes(
                    quarterFilter.value
                )

        return (
            matchesYear
            && matchesSearch
            && matchesUnit
            && matchesQuarter
        )
    })
})


/*
|--------------------------------------------------------------------------
| STOCK BY UNIT SUMMARY
|--------------------------------------------------------------------------
*/

const unitStockSummary = computed(() => {
    const groups = new Map()

    suppliesItems.value
        .filter((item) =>
            Number(item.inventory_year)
                === Number(yearFilter.value)
            &&
            itemMatchesQuarter(
                item,
                quarterFilter.value
            )
        )
        .forEach((item) => {
            const unit =
                String(item.unit || '')
                    .trim()
                    .toUpperCase()

            if (!unit) {
                return
            }

            if (!groups.has(unit)) {
                groups.set(unit, {
                    unit,
                    itemCount: 0,
                    trackedItemCount: 0,
                    totalCurrent: 0,
                    totalReleased: 0,
                })
            }

            const group =
                groups.get(unit)

            const currentValue =
                currentAvailableValue(item)

            const releasedValue =
                quantityReleasedValue(item)

            group.itemCount += 1

            /*
             * Remaining % is now based on ACTUAL stock movement:
             *
             * Currently Available
             * ------------------------------- x 100
             * Currently Available + Released
             *
             * This works for BOTH:
             *
             * 1. Items with Fixed Value
             *    Released = Fixed - Current
             *
             * 2. Items without Fixed Value
             *    Released = tracked_released
             *
             * Therefore Fixed Value is NOT used directly
             * by the graph anymore.
             */
            if (currentValue !== null) {
                group.trackedItemCount += 1

                group.totalCurrent +=
                    Math.max(
                        0,
                        currentValue
                    )

                group.totalReleased +=
                    Math.max(
                        0,
                        Number(
                            releasedValue ?? 0
                        )
                    )
            }
        })

    const unitOrder = new Map(
        suppliesUnitOptions.map(
            (unit, index) => [
                unit.value,
                index,
            ]
        )
    )

    return [...groups.values()]
        .map((group) => {
            const remaining =
                group.totalCurrent

            const accountedTotal =
                group.totalCurrent
                + group.totalReleased

            let percentRemaining = null

            if (group.trackedItemCount > 0) {
                percentRemaining =
                    accountedTotal > 0
                        ? Math.max(
                            0,
                            Math.min(
                                100,
                                Math.round(
                                    (
                                        group.totalCurrent
                                        / accountedTotal
                                    )
                                    * 100
                                )
                            )
                        )
                        : 0
            }

            return {
                ...group,
                remaining,
                accountedTotal,
                percentRemaining,
            }
        })
        .sort((a, b) => {
            const aOrder =
                unitOrder.has(a.unit)
                    ? unitOrder.get(a.unit)
                    : 999

            const bOrder =
                unitOrder.has(b.unit)
                    ? unitOrder.get(b.unit)
                    : 999

            if (aOrder !== bOrder) {
                return aOrder - bOrder
            }

            return a.unit.localeCompare(b.unit)
        })
})

const selectUnitSummary = (unit) => {
    unitFilter.value =
        unitFilter.value === unit
            ? 'all'
            : unit
}

const unitSummaryBarClass = (summary) => {
    if (summary.percentRemaining === null) {
        return 'bg-slate-300'
    }

    if (summary.percentRemaining <= 20) {
        return 'bg-rose-500'
    }

    if (summary.percentRemaining < 50) {
        return 'bg-amber-500'
    }

    return 'bg-emerald-500'
}

const unitSummaryPercentClass = (summary) => {
    if (summary.percentRemaining === null) {
        return 'text-slate-400'
    }

    if (summary.percentRemaining <= 20) {
        return 'text-rose-700'
    }

    if (summary.percentRemaining < 50) {
        return 'text-amber-700'
    }

    return 'text-emerald-700'
}

const unitSummaryCardClass = (summary) => {
    if (unitFilter.value === summary.unit) {
        return 'border-blue-400 bg-blue-50 ring-2 ring-blue-100'
    }

    return 'border-slate-200 bg-white hover:border-blue-200 hover:bg-blue-50/40'
}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

const totalPages = computed(() => {
    return Math.max(
        1,
        Math.ceil(
            filteredItems.value.length
            / perPage
        )
    )
})

const paginatedItems = computed(() => {
    const start =
        (currentPage.value - 1)
        * perPage

    return filteredItems.value.slice(
        start,
        start + perPage
    )
})

const showingFrom = computed(() => {
    if (!filteredItems.value.length) {
        return 0
    }

    return (
        (currentPage.value - 1)
        * perPage
    ) + 1
})

const showingTo = computed(() => {
    return Math.min(
        currentPage.value * perPage,
        filteredItems.value.length
    )
})

/*
|--------------------------------------------------------------------------
| COUNTERS
|--------------------------------------------------------------------------
*/

const withRemarksCount = computed(() => {
    return currentItems.value.filter(
        (item) =>
            String(
                item.remarks || ''
            ).trim() !== ''
    ).length
})

const lowStockCount = computed(() => {
    if (
        activeTab.value !== 'supplies'
    ) {
        return 0
    }

    return suppliesItems.value.filter((item) => {
        if (
            Number(item.inventory_year)
                !== Number(yearFilter.value)
        ) {
            return false
        }

        if (
            !itemMatchesQuarter(
                item,
                quarterFilter.value
            )
        ) {
            return false
        }

        const remaining = differenceValue(item)

        return (
            remaining !== null
            && remaining <= 3
        )
    }).length
})

/*
|--------------------------------------------------------------------------
| TAB / WATCH
|--------------------------------------------------------------------------
*/

const switchTab = (tab) => {
    activeTab.value = tab

    search.value = ''
    unitFilter.value = 'all'
    quarterFilter.value = 'all'
    currentPage.value = 1
}

watch(
    [
        search,
        yearFilter,
        unitFilter,
        quarterFilter,
    ],
    () => {
        currentPage.value = 1
    }
)

watch(
    totalPages,
    (pages) => {
        if (
            currentPage.value > pages
        ) {
            currentPage.value = pages
        }
    }
)

/*
|--------------------------------------------------------------------------
| HISTORY MODAL
|--------------------------------------------------------------------------
*/

const openHistoryModal = async (item) => {
    if (!item?.id) {
        return
    }

    historyItem.value = item
    inventoryHistories.value = []
    historyError.value = ''
    historyLoading.value = true
    showHistoryModal.value = true

    try {
        const response = await fetch(
            `/dts/inventory/${item.id}/history`,
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        )

        if (!response.ok) {
            throw new Error(
                `Unable to load history (${response.status}).`
            )
        }

        const data = await response.json()

        historyItem.value = {
            ...item,
            ...(data?.item || {}),
        }

        inventoryHistories.value =
            Array.isArray(data?.histories)
                ? data.histories
                : []
    } catch (error) {
        historyError.value =
            error?.message
            || 'Unable to load update history.'
    } finally {
        historyLoading.value = false
    }
}

const closeHistoryModal = () => {
    showHistoryModal.value = false
    historyItem.value = null
    inventoryHistories.value = []
    historyError.value = ''
    historyLoading.value = false
}


/*
|--------------------------------------------------------------------------
| HISTORY DISPLAY HELPERS
|--------------------------------------------------------------------------
*/

const numericAvailable = (value) => {
    const match =
        String(value ?? '')
            .match(
                /-?\d+(?:\.\d+)?/
            )

    return match
        ? Number(match[0])
        : 0
}

const historyOldCurrentAvailable = (history) => {
    const value =
        history?.old_currently_available
        ?? history?.old_available
        ?? history?.legacy_old_available

    if (
        value === null
        || value === undefined
        || String(value).trim() === ''
    ) {
        return null
    }

    return numericAvailable(value)
}

const historyNewCurrentAvailable = (history) => {
    const value =
        history?.new_currently_available
        ?? history?.new_available
        ?? history?.legacy_new_available

    if (
        value === null
        || value === undefined
        || String(value).trim() === ''
    ) {
        return null
    }

    return numericAvailable(value)
}

const historyReleasedChanged = (history) => {
    const oldValue =
        historyOldCurrentAvailable(history)

    const newValue =
        historyNewCurrentAvailable(history)

    return (
        oldValue !== null
        && newValue !== null
        && oldValue !== newValue
    )
}

const historyRemarksChanged = (history) => {
    return String(
        history?.old_remarks ?? ''
    ).trim() !== String(
        history?.new_remarks ?? ''
    ).trim()
}

/*
 * Current stock goes DOWN when an item is released.
 * Example:
 * old current = 11
 * new current = 8
 * released = 11 - 8 = 3
 */
const historyQuantityDeltaValue = (history) => {
    const oldValue =
        historyOldCurrentAvailable(history)

    const newValue =
        historyNewCurrentAvailable(history)

    if (
        oldValue === null
        || newValue === null
    ) {
        return 0
    }

    return oldValue - newValue
}

const historyQuantityLabel = (history) => {
    return historyQuantityDeltaValue(history) >= 0
        ? 'Quantity Released'
        : 'Stock Adjustment'
}

const historyQuantityDisplay = (history) => {
    const delta =
        historyQuantityDeltaValue(history)

    return delta >= 0
        ? `${delta}`
        : `+${Math.abs(delta)}`
}

const historyRemarksText = (history) => {
    const newRemarks =
        String(
            history?.new_remarks ?? ''
        ).trim()

    return newRemarks || 'Remarks removed'
}

const historyHasDetailedChanges = (history) => {
    return (
        Array.isArray(history?.changes)
        && history.changes.length > 0
    )
}

const historyActionLabel = (history) => {
    if (history?.action === 'release') {
        return 'Released stock'
    }

    if (history?.action === 'edit') {
        return 'Edited item'
    }

    return 'Updated inventory'
}

const historyChangeValue = (change, value) => {
    if (
        value === null
        || value === undefined
        || String(value).trim() === ''
    ) {
        return '—'
    }

    const field =
        String(change?.field || '')

    if (field === 'quarters') {
        const quarters =
            Array.isArray(value)
                ? value
                : []

        return quarters.length
            ? quarters
                .map((quarter) =>
                    String(quarter).toUpperCase()
                )
                .join(', ')
            : '—'
    }

    if (field === 'category') {
        const category =
            String(value)
                .trim()
                .toLowerCase()

        return category === 'ict'
            ? 'ICT & Other Items'
            : category === 'supplies'
                ? 'Supplies'
                : String(value)
    }

    if (
        [
            'inventory_year',
            'fixed_value',
            'currently_available',
            'release_quantity',
        ].includes(field)
        && !Number.isNaN(Number(value))
    ) {
        return Number(value).toLocaleString()
    }

    return String(value)
}

/*
|--------------------------------------------------------------------------
| STOCK HELPERS
|--------------------------------------------------------------------------
*/

const currentAvailableValue = (item) => {
    const value =
        item?.currently_available

    if (
        value === null
        || value === undefined
        || String(value).trim() === ''
    ) {
        return null
    }

    const quantity = Number(value)

    return Number.isFinite(quantity)
        ? quantity
        : null
}

const hasFixedBaseline = (item) => {
    const raw =
        item?.fixed
        ?? item?.fixed_value

    if (
        raw === null
        || raw === undefined
        || String(raw).trim() === ''
    ) {
        return false
    }

    const value = Number(raw)

    return Number.isFinite(value)
        && value > 0
}

const quantityReleasedValue = (item) => {
    /*
     * WITH a usable Fixed Value:
     * Quantity Released = Fixed Value - Currently Available.
     */
    if (hasFixedBaseline(item)) {
        const generated =
            item?.total_released

        if (
            generated !== null
            && generated !== undefined
            && String(generated).trim() !== ''
        ) {
            const quantity = Number(generated)

            if (Number.isFinite(quantity)) {
                return Math.max(0, quantity)
            }
        }

        const fixed = Number(
            item?.fixed
            ?? item?.fixed_value
        )

        const currentlyAvailable =
            currentAvailableValue(item)

        if (currentlyAvailable === null) {
            return null
        }

        return Math.max(
            0,
            fixed - currentlyAvailable
        )
    }

    /*
     * WITHOUT a usable Fixed Value (NULL/blank/0):
     * Quantity Released is tracked from system releases only.
     * New rows start at 0.
     */
    const tracked = Number(
        item?.tracked_released ?? 0
    )

    return Number.isFinite(tracked)
        ? Math.max(0, tracked)
        : 0
}

/*
 * Kept as differenceValue because the template already uses
 * this helper in several places.
 *
 * It now means ACTUAL Currently Available in SPD.
 */
const differenceValue = (item) => {
    return currentAvailableValue(item)
}

const differenceClass = (item) => {
    const current =
        currentAvailableValue(item)

    if (current === null) {
        return 'border-slate-200 bg-slate-50 text-slate-400'
    }

    if (current <= 0) {
        return 'border-rose-200 bg-rose-50 text-rose-700'
    }

    if (current <= 3) {
        return 'border-amber-200 bg-amber-50 text-amber-700'
    }

    return 'border-emerald-200 bg-emerald-50 text-emerald-700'
}

/*
|--------------------------------------------------------------------------
| EDIT ITEM MODAL — RELEASE QUANTITY
|--------------------------------------------------------------------------
*/

const allFullEditQuartersSelected = computed(() => {
    return quarterValues.every((quarter) =>
        fullEditForm.value.quarters.includes(quarter)
    )
})

const toggleFullEditQuarter = (quarter) => {
    const current = [...fullEditForm.value.quarters]

    fullEditForm.value.quarters =
        current.includes(quarter)
            ? current.filter((value) => value !== quarter)
            : [...current, quarter]
}

const toggleAllFullEditQuarters = () => {
    fullEditForm.value.quarters =
        allFullEditQuartersSelected.value
            ? []
            : [...quarterValues]
}

const openFullEditModal = (item) => {
    if (!item?.id) {
        return
    }

    const normalized = normalizeInventoryItem(item)

    fullEditingItem.value = normalized
    fullEditForm.value = {
        category: normalized.category || 'supplies',
        item: String(normalized.item || ''),
        unit: String(normalized.unit || ''),
        inventory_year: Number(normalized.inventory_year) || 2026,
        fixed_value:
            normalized.fixed === null
            || normalized.fixed === undefined
                ? ''
                : normalized.fixed,
        currently_available:
            normalized.currently_available === null
            || normalized.currently_available === undefined
                ? ''
                : normalized.currently_available,
        quarters: [...inferredItemQuarters(normalized)],

        // Always start Edit Remarks blank.
        // Previous remarks should not be preloaded into a new edit session.
        remarks: '',
    }

    fullEditErrors.value = {}
    showFullEditModal.value = true
}

const closeFullEditModal = () => {
    showFullEditModal.value = false
    fullEditingItem.value = null
    fullEditErrors.value = {}
}

const saveFullEditItem = () => {
    const original = fullEditingItem.value

    if (!original?.id) {
        return
    }

    fullEditErrors.value = {}

    const category = String(
        fullEditForm.value.category || ''
    ).trim().toLowerCase()

    const itemName = String(
        fullEditForm.value.item || ''
    ).trim()

    const unit = String(
        fullEditForm.value.unit || ''
    ).trim().toUpperCase()

    const inventoryYear = Number(
        fullEditForm.value.inventory_year
    )

    const quarters = [
        ...new Set(fullEditForm.value.quarters),
    ].filter((quarter) =>
        quarterValues.includes(quarter)
    )

    const isSupplies = category === 'supplies'

    const fixedRaw = fullEditForm.value.fixed_value
    const hasFixed =
        fixedRaw !== ''
        && fixedRaw !== null
        && fixedRaw !== undefined
    const fixedValue = hasFixed
        ? Number(fixedRaw)
        : null

    const currentRaw =
        fullEditForm.value.currently_available
    const hasCurrent =
        currentRaw !== ''
        && currentRaw !== null
        && currentRaw !== undefined
    const currentValue = hasCurrent
        ? Number(currentRaw)
        : null

    if (!['supplies', 'ict'].includes(category)) {
        fullEditErrors.value.category =
            'Select a valid category.'
    }

    if (!itemName) {
        fullEditErrors.value.item =
            'Item name is required.'
    }

    if (!unit) {
        fullEditErrors.value.unit =
            'Unit of measure is required.'
    }

    if (!Number.isInteger(inventoryYear) || inventoryYear < 2026) {
        fullEditErrors.value.inventory_year =
            'Select a valid year from 2026 onwards.'
    }

    if (!quarters.length) {
        fullEditErrors.value.quarters =
            'Select at least one applicable quarter.'
    }

    if (isSupplies) {
        if (
            hasFixed
            && (!Number.isFinite(fixedValue) || fixedValue < 0)
        ) {
            fullEditErrors.value.fixed_value =
                'Enter a valid Fixed Value or leave it blank.'
        }

        if (
            !hasCurrent
            || !Number.isFinite(currentValue)
            || currentValue < 0
        ) {
            fullEditErrors.value.currently_available =
                'Currently Available is required.'
        }
    }

    const duplicateExists =
        normalizedInventoryItems.value.some((item) =>
            Number(item.id) !== Number(original.id)
            && item.category === category
            && Number(item.inventory_year) === inventoryYear
            && String(item.item || '').trim().toLowerCase()
                === itemName.toLowerCase()
            && String(item.unit || '').trim().toUpperCase()
                === unit
        )

    if (duplicateExists) {
        fullEditErrors.value.item =
            `This item and unit already exist for ${inventoryYear}.`
    }

    if (Object.keys(fullEditErrors.value).length) {
        return
    }

    router.put(
        `/dts/inventory/${original.id}`,
        {
            category,
            item: itemName,
            unit,
            inventory_year: inventoryYear,
            quarters,
            fixed_value: isSupplies ? fixedValue : null,
            currently_available: isSupplies ? currentValue : null,
            remarks:
                String(fullEditForm.value.remarks || '').trim()
                || null,
        },
        {
            preserveScroll: true,

            onSuccess: () => {
                activeTab.value = category
                yearFilter.value = inventoryYear
                quarterFilter.value = 'all'
                unitFilter.value = 'all'
                search.value = ''
                currentPage.value = 1

                closeFullEditModal()

                router.reload({
                    only: ['inventoryItems'],
                    preserveScroll: true,
                    preserveState: true,
                })
            },

            onError: (errors) => {
                fullEditErrors.value = { ...errors }
            },
        }
    )
}

const releaseTotalReleased = computed(() => {
    return quantityReleasedValue(
        releasingItem.value
    ) ?? 0
})

const releaseFixedValue = computed(() => {
    const raw =
        releasingItem.value?.fixed
        ?? releasingItem.value?.fixed_value

    if (
        raw === null
        || raw === undefined
        || String(raw).trim() === ''
    ) {
        return null
    }

    const value = Number(raw)

    return Number.isFinite(value)
        ? value
        : null
})

const releaseHasFixedBaseline = computed(() => {
    return hasFixedBaseline(
        releasingItem.value
    )
})

const releaseCurrentAvailable = computed(() => {
    return currentAvailableValue(
        releasingItem.value
    ) ?? 0
})

const releaseQuantity = computed(() => {
    const value = Number(
        releaseItemForm.value.releaseQuantity
    )

    return Number.isFinite(value)
        && value > 0
        ? value
        : 0
})

const releaseRemainingQuantity = computed(() => {
    return Math.max(
        0,
        releaseCurrentAvailable.value
        - releaseQuantity.value
    )
})

const releaseTotalReleasedAfter = computed(() => {
    if (!releaseHasFixedBaseline.value) {
        return (
            releaseTotalReleased.value
            + releaseQuantity.value
        )
    }

    return Math.max(
        0,
        releaseFixedValue.value
        - releaseRemainingQuantity.value
    )
})

const openReleaseItemModal = (item) => {
    if (!item?.id) {
        return
    }

    releasingItem.value = item

    /*
     * Every release is a NEW transaction.
     * Do not preload the previous/item remarks.
     */
    releaseItemForm.value = {
        releaseQuantity: '',
        remarks: '',
    }

    releaseItemErrors.value = {}
    showReleaseItemModal.value = true
}

const closeReleaseItemModal = () => {
    showReleaseItemModal.value = false
    releasingItem.value = null

    releaseItemForm.value = {
        releaseQuantity: '',
        remarks: '',
    }

    releaseItemErrors.value = {}
}

const saveReleaseItem = () => {
    const item = releasingItem.value

    if (!item?.id) {
        return
    }

    releaseItemErrors.value = {}

    const releaseQuantity =
        Number(
            releaseItemForm.value.releaseQuantity
        )

    if (
        !Number.isFinite(releaseQuantity)
        || releaseQuantity <= 0
    ) {
        releaseItemErrors.value.releaseQuantity =
            'Enter the quantity to release.'

        return
    }

    if (
        releaseQuantity
        > releaseCurrentAvailable.value
    ) {
        releaseItemErrors.value.releaseQuantity =
            `Only ${releaseCurrentAvailable.value} item(s) are currently available.`

        return
    }

    /*
     * IMPORTANT:
     * Do not send total_released.
     * It is generated automatically:
     *
     * total_released =
     * fixed_value - currently_available
     *
     * The controller only needs the release quantity,
     * then it subtracts that value from currently_available.
     */
    /*
     * Calculate the expected next values for immediate UI feedback.
     * The DB remains the source of truth; router.reload() follows.
     */
    const nextCurrent =
        Math.max(
            0,
            releaseCurrentAvailable.value
            - releaseQuantity
        )

    const nextTrackedReleased =
        releaseHasFixedBaseline.value
            ? Number(
                item.tracked_released ?? 0
            )
            : (
                Number(
                    item.tracked_released ?? 0
                )
                + releaseQuantity
            )

    const nextGeneratedReleased =
        releaseHasFixedBaseline.value
            ? Math.max(
                0,
                Number(releaseFixedValue.value)
                - nextCurrent
            )
            : null

    const releaseRemarks =
        String(
            releaseItemForm.value.remarks ?? ''
        ).trim()

    router.put(
        `/dts/inventory/${item.id}`,
        {
            release_quantity:
                releaseQuantity,

            /*
             * Transaction remark only.
             * Controller stores this in History,
             * not as the item's permanent remarks.
             */
            remarks:
                releaseRemarks,
        },
        {
            preserveScroll: true,

            onSuccess: () => {
                /*
                 * Update the visible row immediately so:
                 * - Currently Available changes
                 * - Remaining bar changes
                 * - Quantity Released changes
                 */
                updateLocalInventoryItem(
                    item.id,
                    {
                        currently_available:
                            nextCurrent,

                        tracked_released:
                            Math.max(
                                0,
                                nextTrackedReleased
                            ),

                        total_released:
                            nextGeneratedReleased,
                    }
                )

                closeReleaseItemModal()

                /*
                 * Force a fresh copy from Laravel/MySQL.
                 * This guarantees the local values are replaced
                 * by the authoritative generated/tracked values.
                 */
                router.reload({
                    only: [
                        'inventoryItems',
                    ],
                    preserveScroll: true,
                    preserveState: true,
                })
            },

            onError: (errors) => {
                releaseItemErrors.value = {
                    ...errors,
                }
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| UI HELPERS
|--------------------------------------------------------------------------
*/

const unitBadgeClass = (unit) => {
    if (unit === 'REAM') {
        return 'bg-violet-50 text-violet-700 border-violet-200'
    }

    if (unit === 'PACK') {
        return 'bg-blue-50 text-blue-700 border-blue-200'
    }

    if (unit === 'BOX') {
        return 'bg-lime-50 text-lime-700 border-lime-200'
    }

    if (unit === 'PIECE') {
        return 'bg-cyan-50 text-cyan-700 border-cyan-200'
    }

    if (unit === 'LOT') {
        return 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200'
    }

    if (unit === 'PAX') {
        return 'bg-orange-50 text-orange-700 border-orange-200'
    }

    if (unit === 'UNIT') {
        return 'bg-sky-50 text-sky-700 border-sky-200'
    }

    if (unit === 'ROLL') {
        return 'bg-rose-50 text-rose-700 border-rose-200'
    }

    if (unit === 'TUBE') {
        return 'bg-teal-50 text-teal-700 border-teal-200'
    }

    if (unit === 'PAD') {
        return 'bg-amber-50 text-amber-700 border-amber-200'
    }

    if (unit === 'BOOK') {
        return 'bg-indigo-50 text-indigo-700 border-indigo-200'
    }

    if (unit === 'SETS') {
        return 'bg-purple-50 text-purple-700 border-purple-200'
    }

    if (unit === 'BUNDLE') {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    }

    return 'bg-slate-50 text-slate-700 border-slate-200'
}

const availabilityClass = (value) => {
    if (
        value === null
        || value === undefined
        || String(value).trim() === ''
    ) {
        return 'bg-slate-50 text-slate-400 border-slate-200'
    }

    const quantity = numericAvailable(value)

    if (quantity <= 0) {
        return 'bg-red-50 text-red-700 border-red-200'
    }

    if (quantity <= 3) {
        return 'bg-amber-50 text-amber-700 border-amber-200'
    }

    return 'bg-emerald-50 text-emerald-700 border-emerald-200'
}

/*
|--------------------------------------------------------------------------
| LEDGER DISPLAY HELPERS
|--------------------------------------------------------------------------
*/

const displayInventoryItem = (item) => {
    return item
}

const remainingPercent = (item) => {
    const displayItem =
        displayInventoryItem(item)

    const current =
        currentAvailableValue(displayItem)

    if (current === null) {
        return 0
    }

    const released =
        Math.max(
            0,
            Number(
                quantityReleasedValue(
                    displayItem
                ) ?? 0
            )
        )

    /*
     * Same rule as the Stock by Unit graph:
     *
     * Current
     * -------------------- x 100
     * Current + Released
     *
     * No direct dependency on Fixed Value.
     */
    const accountedTotal =
        Math.max(0, current)
        + released

    if (accountedTotal <= 0) {
        return 0
    }

    return Math.max(
        0,
        Math.min(
            100,
            Math.round(
                (
                    Math.max(0, current)
                    / accountedTotal
                )
                * 100
            )
        )
    )
}

const stockStatusLabel = (item) => {
    const remaining = differenceValue(
        displayInventoryItem(item)
    )

    if (remaining === null) {
        return 'No data'
    }

    if (remaining <= 0) {
        return 'Depleted'
    }

    if (remaining <= 3) {
        return 'Low'
    }

    return 'Available'
}

const stockStatusClass = (item) => {
    const status = stockStatusLabel(item)

    if (status === 'Depleted') {
        return 'border-rose-200 bg-rose-50 text-rose-700'
    }

    if (status === 'Low') {
        return 'border-amber-200 bg-amber-50 text-amber-700'
    }

    if (status === 'Available') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    }

    return 'border-slate-200 bg-slate-50 text-slate-400'
}

const remainingBarClass = (item) => {
    const status = stockStatusLabel(item)

    if (status === 'Depleted') {
        return 'bg-rose-500'
    }

    if (status === 'Low') {
        return 'bg-amber-500'
    }

    if (status === 'Available') {
        return 'bg-emerald-500'
    }

    return 'bg-slate-300'
}

</script>

<template>
    <Head title="Inventory" />

    <div class="min-h-screen bg-[#f7faff]">
        <main class="mx-auto max-w-[1700px] px-4 py-5 sm:px-6 lg:px-8">
            <!-- COMPACT LEDGER HEADER -->
            <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-5 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-sm shadow-blue-200">
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M4 7h16" />
                                    <path d="M4 12h16" />
                                    <path d="M4 17h16" />
                                    <path d="M8 4v16" />
                                </svg>
                            </div>

                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-600">
                                    DTS · Inventory Module
                                </p>

                                <h1 class="mt-0.5 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                                    Inventory Monitoring
                                </h1>
                            </div>
                        </div>

                       
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-500 px-5 text-sm font-black text-white shadow-sm shadow-blue-100 transition hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                        @click="openAddItemModal"
                    >
                        <span class="text-lg leading-none">+</span>
                        <span>Add Item</span>
                    </button>
                </div>

                <!-- SEGMENTED TABS -->
                <div class="border-t border-slate-100 bg-slate-50/70 px-5 py-3 sm:px-6">
                    <div class="inline-flex w-full rounded-xl border border-slate-200 bg-white p-1 sm:w-auto">
                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-black transition sm:min-w-[190px]"
                            :class="
                                activeTab === 'supplies'
                                    ? 'bg-blue-500 text-white shadow-sm shadow-blue-100'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-blue-700'
                            "
                            @click="switchTab('supplies')"
                        >
                            <span>Supplies</span>
                        </button>

                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-black transition sm:min-w-[260px]"
                            :class="
                                activeTab === 'ict'
                                    ? 'bg-blue-500 text-white shadow-sm shadow-blue-100'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-blue-700'
                            "
                            @click="switchTab('ict')"
                        >
                            <span>ICT & Other Items     </span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- LEDGER WORKSPACE -->
            <section class="mt-4 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
                <!-- TOOLBAR -->
                <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-black text-slate-900">
                                    {{ currentTitle }}
                                </h2>

                               

                                <span
                                    v-if="
                                        quarterFilter !== 'all'
                                    "
                                    class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-black text-blue-700"
                                >
                                    {{ quarterFilter.toUpperCase() }}
                                </span>
                            </div>
                           
                        </div>

                        <div
                            class="grid w-full grid-cols-1 gap-2 sm:grid-cols-2 xl:w-[960px] xl:grid-cols-[minmax(0,1fr)_120px_140px_150px]"
                        >
                            <!-- SEARCH -->
                            <div class="relative sm:col-span-2 xl:col-span-1">
                                <svg
                                    class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <circle cx="11" cy="11" r="8" />
                                    <path d="m21 21-4.35-4.35" />
                                </svg>

                                <input
                                    v-model="search"
                                    type="text"
                                    :placeholder="
                                        activeTab === 'supplies'
                                            ? 'Search supplies...'
                                            : 'Search ICT and other items...'
                                    "
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-xs font-semibold text-slate-700 outline-none transition focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-100"
                                />
                            </div>

                            <select
                                v-model.number="yearFilter"
                                class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            >
                                <option
                                    v-for="year in yearOptions"
                                    :key="`filter-year-${year}`"
                                    :value="year"
                                >
                                    {{ year }}
                                </option>
                            </select>

                            <select
                                v-model="unitFilter"
                                class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            >
                                <option value="all">All units</option>

                                <option
                                    v-for="unit in unitOptions"
                                    :key="unit.value"
                                    :value="unit.value"
                                >
                                    {{ unit.label }}
                                </option>
                            </select>

                            <select
                                v-model="quarterFilter"
                                class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            >
                                <option
                                    v-for="quarter in quarterOptions"
                                    :key="quarter.value"
                                    :value="quarter.value"
                                >
                                    {{ quarter.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- STOCK BY UNIT VERTICAL BAR GRAPH -->
                <div
                    v-if="activeTab === 'supplies'"
                    class="border-b border-slate-200 bg-slate-50/60 px-5 py-4 sm:px-6"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-black text-slate-900">
                                    Stock by Unit
                                </h3>

                                <span
                                    v-if="unitFilter !== 'all'"
                                    class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[9px] font-black text-blue-700"
                                >
                                    Filtering: {{ unitFilter }}
                                </span>
                            </div>

                            <p class="mt-1 text-[10px] font-semibold text-slate-400">
                                Remaining stock based on Currently Available in SPD and Quantity Released. Click a bar to filter the table.
                            </p>
                        </div>

                        <button
                            v-if="unitFilter !== 'all'"
                            type="button"
                            class="self-start text-[10px] font-black text-blue-600 transition hover:text-blue-700 sm:self-auto"
                            @click="unitFilter = 'all'"
                        >
                            Show all units
                        </button>
                    </div>

                    <div
                        v-if="unitStockSummary.length"
                        class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5"
                    >
                        <!-- VERTICAL BARS -->
                        <div
                            class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10"
                        >
                            <button
                                v-for="summary in unitStockSummary"
                                :key="`unit-vertical-bar-${summary.unit}`"
                                type="button"
                                class="group min-w-0 rounded-xl border px-2 py-3 text-center transition"
                                :class="
                                    unitFilter === summary.unit
                                        ? 'border-blue-300 bg-blue-50 ring-2 ring-blue-100'
                                        : 'border-slate-100 bg-white hover:border-blue-200 hover:bg-blue-50/30'
                                "
                                @click="selectUnitSummary(summary.unit)"
                            >
                                <!-- PERCENT -->
                                <p
                                    class="text-sm font-black tabular-nums sm:text-base"
                                    :class="unitSummaryPercentClass(summary)"
                                >
                                    {{
                                        summary.percentRemaining === null
                                            ? '—'
                                            : `${summary.percentRemaining}%`
                                    }}
                                </p>

                                <!-- BAR AREA -->
                                <div
                                    class="mx-auto mt-2 flex h-28 w-8 items-end overflow-hidden bg-slate-100 sm:h-32 sm:w-9"
                                >
                                    <div
                                        class="w-full transition-all duration-500"
                                        :class="unitSummaryBarClass(summary)"
                                        :style="{
                                            height: `${
                                                summary.percentRemaining === null
                                                    ? 0
                                                    : summary.percentRemaining
                                            }%`,
                                        }"
                                    ></div>
                                </div>

                                <!-- UNIT -->
                                <p
                                    class="mt-2 truncate text-[10px] font-black uppercase tracking-[0.08em] text-black sm:text-[11px]"
                                >
                                    {{ summary.unit }}
                                </p>

                                <!-- DETAILS -->
                                <p
                                    class="mt-1 text-[10px] font-black text-black sm:text-[11px]"
                                >
                                    {{ summary.remaining }} available
                                </p>

                                <p
                                    class="mt-0.5 text-[9px] font-bold text-slate-500 sm:text-[10px]"
                                >
                                    {{ summary.totalReleased }} released
                                </p>

                                <p
                                    class="mt-0.5 text-[9px] font-bold text-black sm:text-[10px]"
                                >
                                    {{ summary.itemCount }} item(s)
                                </p>
                            </button>
                        </div>

                        <!-- LEGEND -->
                        <div
                            class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-slate-100 pt-3 text-[16px] font-bold text-slate-400"
                        >
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                50–100%
                            </span>

                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                21–49%
                            </span>

                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                0–20%
                            </span>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-5 text-center"
                    >
                        <p class="text-xs font-bold text-slate-400">
                            No unit stock data available for this selection.
                        </p>
                    </div>
                </div>

                <!-- DESKTOP LEDGER -->
                <div v-if="activeTab === 'supplies'" class="hidden overflow-hidden lg:block">
                    <table class="w-full table-fixed">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th class="w-[20%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.10em]">
                                    Item
                                </th>

                                <th class="w-[7%] px-2 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Unit
                                </th>

                                <th class="w-[8%] px-2 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Fixed Value
                                </th>

                                <th class="w-[11%] px-2 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Quantity Released
                                </th>

                                <th class="w-[16%] bg-blue-600 px-2 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Currently Available in SPD
                                </th>

                                <th class="w-[22%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.10em]">
                                    Remarks
                                </th>

                                <th class="w-[16%] px-2 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in paginatedItems"
                                :key="`${activeTab}-${item.id || item.item}`"
                                class="bg-white transition hover:bg-blue-50/35"
                            >
                                <!-- ITEM -->
                                <td class="px-3 py-3 align-middle">
                                    <p class="break-words text-xs font-black leading-4 text-slate-900">
                                        {{ item.item }}
                                    </p>

                                    <div
                                        v-if="quarterBadges(item).length"
                                        class="mt-2 flex flex-wrap gap-1"
                                    >
                                        <span
                                            v-for="quarter in quarterBadges(item)"
                                            :key="`${activeTab}-${item.item}-${quarter}`"
                                            class="rounded-md border border-blue-100 bg-blue-50 px-2 py-0.5 text-[9px] font-black text-blue-700"
                                        >
                                            {{ quarter }}
                                        </span>
                                    </div>
                                </td>

                                <!-- UNIT -->
                                <td class="px-2 py-3 text-center align-middle">
                                    <span
                                        class="inline-flex rounded-lg border px-2.5 py-1 text-[9px] font-black"
                                        :class="unitBadgeClass(item.unit)"
                                    >
                                        {{ item.unit }}
                                    </span>
                                </td>

                                <!-- FIXED -->
                                <td class="px-2 py-3 text-center align-middle">
                                    <span
                                        v-if="hasFixedBaseline(item)"
                                        class="text-sm font-black tabular-nums text-slate-800"
                                    >
                                        {{ item.fixed }}
                                    </span>

                                    <span v-else class="text-sm font-semibold text-slate-300">
                                        —
                                    </span>
                                </td>

                                <!-- RELEASED -->
                                <td class="px-2 py-3 text-center align-middle">
                                    <span
                                        class="inline-flex min-w-10 justify-center rounded-lg border border-blue-100 bg-blue-50 px-2.5 py-1.5 text-xs font-black tabular-nums text-blue-700"
                                    >
                                        {{
                                            quantityReleasedValue(item)
                                            ?? '—'
                                        }}
                                    </span>

                                    <p
                                        v-if="!hasFixedBaseline(item)"
                                        class="mt-1 text-[8px] font-bold leading-3 text-slate-400"
                                    >
                                        System tracked
                                    </p>
                                </td>

                                <!-- CURRENTLY AVAILABLE -->
                                <td class="bg-slate-50/70 px-2 py-3 align-middle">
                                    <div
                                        v-if="differenceValue(displayInventoryItem(item)) !== null"
                                        class="mx-auto max-w-[150px]"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-lg font-black tabular-nums text-slate-900">
                                                {{ differenceValue(displayInventoryItem(item)) }}
                                            </span>

                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[9px] font-black"
                                                :class="stockStatusClass(item)"
                                            >
                                                {{ stockStatusLabel(item) }}
                                            </span>
                                        </div>

                                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                            <div
                                                class="h-full rounded-full transition-all duration-300"
                                                :class="remainingBarClass(item)"
                                                :style="{
                                                    width: `${remainingPercent(item)}%`,
                                                }"
                                            ></div>
                                        </div>

                                        <p class="mt-1 text-[9px] font-bold text-slate-400">
                                            {{ remainingPercent(item) }}% remaining
                                        </p>
                                    </div>

                                    <span
                                        v-else
                                        class="block text-center text-sm font-semibold text-slate-300"
                                    >
                                        —
                                    </span>
                                </td>

                                <!-- REMARKS -->
                                <td class="px-3 py-3 align-middle">
                                    <p
                                        v-if="item.remarks"
                                        class="break-words text-[11px] font-semibold leading-4 text-slate-600"
                                    >
                                        {{ item.remarks }}
                                    </p>

                                    <span
                                        v-else
                                        class="text-xs font-semibold text-slate-300"
                                    >
                                        —
                                    </span>
                                </td>

                                <!-- ACTION -->
                                <td class="px-2 py-3 text-center align-middle">
                                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[9px] font-black text-slate-700 transition hover:bg-slate-50"
                                            @click="openFullEditModal(item)"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-[9px] font-black text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="
                                                currentAvailableValue(item) === null
                                                || Number(currentAvailableValue(item)) <= 0
                                            "
                                            @click="openReleaseItemModal(item)"
                                        >
                                            Release
                                        </button>

                                        <button
                                            type="button"
                                            title="View History"
                                            aria-label="View History"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-slate-200"
                                            @click="openHistoryModal(item)"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true"
                                            >
                                                <path d="M20 6v5h-5" />
                                                <path d="M19 11a7 7 0 1 0 1 4" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!paginatedItems.length">
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            aria-hidden="true"
                                        >
                                            <circle cx="11" cy="11" r="8" />
                                            <path d="m21 21-4.35-4.35" />
                                        </svg>
                                    </div>

                                    <p class="mt-4 text-sm font-black text-slate-700">
                                        No items found
                                    </p>

                                    <p
                                        v-if="quarterFilter !== 'all'"
                                        class="mt-1 text-xs font-semibold text-slate-400"
                                    >
                                        No items are assigned to {{ quarterFilter.toUpperCase() }}.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                
                <!-- ICT & OTHER ITEMS — YEAR / QUARTER TABLE -->
                <div
                    v-if="activeTab === 'ict'"
                    class="hidden overflow-hidden lg:block"
                >
                    <table class="w-full table-fixed">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th class="w-[32%] px-4 py-3 text-left text-[9px] font-black uppercase tracking-[0.10em]">
                                    Item
                                </th>

                                <th class="w-[14%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Unit of Measure
                                </th>

                                <th class="w-[16%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Quarter(s)
                                </th>

                                <th class="w-[22%] px-4 py-3 text-left text-[9px] font-black uppercase tracking-[0.10em]">
                                    Remarks
                                </th>


                                <th class="w-[16%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="item in paginatedItems"
                                :key="`ict-${item.id || item.item}`"
                                class="bg-white transition hover:bg-blue-50/35"
                            >
                                <td class="px-4 py-4 align-middle">
                                    <p class="break-words text-xs font-black leading-5 text-slate-900">
                                        {{ item.item }}
                                    </p>
                                </td>

                                <td class="px-3 py-4 text-center align-middle">
                                    <span
                                        class="inline-flex rounded-lg border px-2.5 py-1 text-[9px] font-black"
                                        :class="unitBadgeClass(item.unit)"
                                    >
                                        {{ item.unit || '—' }}
                                    </span>
                                </td>

                                <td class="px-3 py-4 text-center align-middle">
                                    <div class="flex flex-wrap justify-center gap-1">
                                        <span
                                            v-for="quarter in quarterBadges(item)"
                                            :key="`ict-quarter-${item.id}-${quarter}`"
                                            class="rounded-md border border-blue-100 bg-blue-50 px-2 py-0.5 text-[9px] font-black text-blue-700"
                                        >
                                            {{ quarter }}
                                        </span>

                                        <span
                                            v-if="!quarterBadges(item).length"
                                            class="text-xs font-semibold text-slate-300"
                                        >
                                            —
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-4 align-middle">
                                    <p
                                        v-if="String(item.remarks || '').trim()"
                                        class="break-words text-[11px] font-semibold leading-5 text-slate-600"
                                    >
                                        {{ item.remarks }}
                                    </p>

                                    <span
                                        v-else
                                        class="text-xs font-semibold text-slate-300"
                                    >
                                        —
                                    </span>
                                </td>


                                <td class="px-3 py-4 text-center align-middle">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black text-slate-700 transition hover:bg-slate-50"
                                        @click="openFullEditModal(item)"
                                    >
                                        Edit
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!paginatedItems.length">
                                <td colspan="5" class="px-6 py-16 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            aria-hidden="true"
                                        >
                                            <circle cx="11" cy="11" r="8" />
                                            <path d="m21 21-4.35-4.35" />
                                        </svg>
                                    </div>

                                    <p class="mt-4 text-sm font-black text-slate-700">
                                        No ICT or other items found
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

<!-- MOBILE LEDGER CARDS -->
                <div v-if="activeTab === 'supplies'" class="space-y-3 p-4 lg:hidden">
                    <article
                        v-for="item in paginatedItems"
                        :key="`mobile-${activeTab}-${item.id || item.item}`"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="border-b border-slate-100 px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="break-words text-sm font-black leading-5 text-slate-900">
                                        {{ item.item }}
                                    </p>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span
                                            class="rounded-md border px-2 py-0.5 text-[9px] font-black"
                                            :class="unitBadgeClass(item.unit)"
                                        >
                                            {{ item.unit }}
                                        </span>

                                        <span
                                            v-for="quarter in quarterBadges(item)"
                                            :key="`mobile-${activeTab}-${item.item}-${quarter}`"
                                            class="rounded-md border border-blue-100 bg-blue-50 px-2 py-0.5 text-[9px] font-black text-blue-700"
                                        >
                                            {{ quarter }}
                                        </span>
                                    </div>
                                </div>

                                <span
                                    class="shrink-0 rounded-full border px-2.5 py-1 text-[9px] font-black"
                                    :class="stockStatusClass(item)"
                                >
                                    {{ stockStatusLabel(item) }}
                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-100">
                            <div class="p-3 text-center">
                                <p class="text-[8px] font-black uppercase tracking-[0.12em] text-slate-400">
                                    Fixed
                                </p>

                                <p class="mt-1 text-base font-black tabular-nums text-slate-900">
                                    {{ hasFixedBaseline(item) ? item.fixed : '—' }}
                                </p>
                            </div>

                            <div class="p-3 text-center">
                                <p class="text-[8px] font-black uppercase tracking-[0.12em] text-blue-500">
                                    Released
                                </p>

                                <p
                                    class="mt-1 text-base font-black tabular-nums text-blue-700"
                                >
                                    {{
                                        quantityReleasedValue(item)
                                        ?? '—'
                                    }}
                                </p>

                            </div>

                            <div class="bg-slate-50 p-3 text-center">
                                <p class="text-[8px] font-black uppercase tracking-[0.12em] text-emerald-600">
                                    Currently Available
                                </p>

                                <p class="mt-1 text-base font-black tabular-nums text-slate-900">
                                    {{ currentAvailableValue(item) ?? '—' }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="
                                differenceValue(displayInventoryItem(item)) !== null
                                && item.fixed !== null
                                && item.fixed !== undefined
                                && Number(item.fixed) > 0
                            "
                            class="px-4 pt-4"
                        >
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-200">
                                <div
                                    class="h-full rounded-full transition-all duration-300"
                                    :class="remainingBarClass(item)"
                                    :style="{
                                        width: `${remainingPercent(item)}%`,
                                    }"
                                ></div>
                            </div>

                            <p class="mt-1 text-right text-[9px] font-bold text-slate-400">
                                {{ remainingPercent(item) }}% remaining
                            </p>
                        </div>

                        <div class="space-y-3 p-4">
                            <div
                                class="rounded-xl border border-slate-100 bg-slate-50 p-3"
                            >
                                <p class="text-[9px] font-black uppercase tracking-[0.13em] text-slate-400">
                                    Remarks
                                </p>

                                <p class="mt-1 text-xs font-semibold leading-5 text-slate-600">
                                    {{ item.remarks || 'No remarks' }}
                                </p>
                            </div>

                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black text-slate-700 transition hover:bg-slate-50"
                                    @click="openFullEditModal(item)"
                                >
                                    Edit
                                </button>

                                <button
                                    type="button"
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-[10px] font-black text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="
                                        currentAvailableValue(item) === null
                                        || Number(currentAvailableValue(item)) <= 0
                                    "
                                    @click="openReleaseItemModal(item)"
                                >
                                    Release
                                </button>

                                <button
                                    type="button"
                                    title="View History"
                                    aria-label="View History"
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700"
                                    @click="openHistoryModal(item)"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="M20 6v5h-5" />
                                        <path d="M19 11a7 7 0 1 0 1 4" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="!paginatedItems.length"
                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
                    >
                        <p class="text-sm font-black text-slate-700">
                            No items found
                        </p>

                        <p
                            v-if="quarterFilter !== 'all'"
                            class="mt-1 text-xs font-semibold text-slate-400"
                        >
                            No items are assigned to {{ quarterFilter.toUpperCase() }}.
                        </p>
                    </div>
                </div>

                
                <!-- ICT & OTHER ITEMS — MOBILE -->
                <div
                    v-if="activeTab === 'ict'"
                    class="space-y-3 p-4 lg:hidden"
                >
                    <article
                        v-for="item in paginatedItems"
                        :key="`mobile-ict-${item.id || item.item}`"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div class="border-b border-slate-100 px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="min-w-0 break-words text-sm font-black leading-5 text-slate-900">
                                    {{ item.item }}
                                </p>

                                <span
                                    class="shrink-0 rounded-md border px-2 py-1 text-[9px] font-black"
                                    :class="unitBadgeClass(item.unit)"
                                >
                                    {{ item.unit || '—' }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-3 p-4">
                            <div class="rounded-xl border border-blue-100 bg-blue-50 p-3">
                                <p class="text-[9px] font-black uppercase tracking-[0.13em] text-blue-500">
                                    Year / Quarter
                                </p>

                                <div class="mt-2 flex flex-wrap items-center gap-1">
                                    <span class="rounded-md bg-white px-2 py-1 text-[10px] font-black text-blue-800 ring-1 ring-blue-100">
                                        {{ item.inventory_year }}
                                    </span>

                                    <span
                                        v-for="quarter in quarterBadges(item)"
                                        :key="`mobile-ict-quarter-${item.id}-${quarter}`"
                                        class="rounded-md bg-white px-2 py-1 text-[10px] font-black text-blue-700 ring-1 ring-blue-100"
                                    >
                                        {{ quarter }}
                                    </span>

                                    <span
                                        v-if="!quarterBadges(item).length"
                                        class="rounded-md bg-white px-2 py-1 text-[10px] font-black text-slate-400 ring-1 ring-blue-100"
                                    >
                                        No quarter
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[9px] font-black uppercase tracking-[0.13em] text-slate-400">
                                    Remarks
                                </p>

                                <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-600">
                                    {{ String(item.remarks || '').trim() || '—' }}
                                </p>
                            </div>


                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-[10px] font-black text-slate-700 transition hover:bg-slate-50"
                                    @click="openFullEditModal(item)"
                                >
                                    Edit
                                </button>
                            </div>
                        </div>
                    </article>

                    <div
                        v-if="!paginatedItems.length"
                        class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
                    >
                        <p class="text-sm font-black text-slate-700">
                            No ICT or other items found
                        </p>
                    </div>
                </div>

<!-- COMPACT PAGINATION -->
                <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/80 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs font-semibold text-slate-500">
                        Showing
                        <span class="font-black text-slate-800">
                            {{ showingFrom }}–{{ showingTo }}
                        </span>
                        of
                        <span class="font-black text-slate-800">
                            {{ filteredItems.length }}
                        </span>
                    </p>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-[10px] font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="currentPage <= 1"
                            @click="currentPage -= 1"
                        >
                            Previous
                        </button>

                        <span class="min-w-16 text-center text-[10px] font-black text-slate-600">
                            {{ currentPage }} / {{ totalPages }}
                        </span>

                        <button
                            type="button"
                            class="rounded-lg bg-blue-600 px-3.5 py-2 text-[10px] font-black text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="currentPage >= totalPages"
                            @click="currentPage += 1"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </main>


        <!-- ADD ITEM MODAL -->
        <div
            v-if="showAddItemModal"
            class="fixed inset-0 z-[60] flex items-end justify-center bg-slate-950/65 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            @click.self="
                closeAddItemModal
            "
        >
            <div
                class="flex max-h-[100dvh] w-full max-w-3xl flex-col overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:max-h-[92vh] sm:rounded-[2rem]"
            >
                <div
                    class="shrink-0 bg-gradient-to-r from-blue-700 to-indigo-700 px-5 py-5 text-white sm:px-6"
                >
                    <div
                        class="flex items-start justify-between gap-4"
                    >
                        <div>
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.18em] text-blue-100"
                            >
                                {{
                                    activeTab === 'supplies'
                                        ? 'Supplies Inventory'
                                        : 'ICT & Other Items'
                                }}
                            </p>

                            <h3
                                class="mt-1 text-2xl font-black"
                            >
                                Add New Item
                            </h3>

                            
                        </div>

                        <button
                            type="button"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15 text-xl font-black hover:bg-white/25"
                            @click="
                                closeAddItemModal
                            "
                        >
                            ×
                        </button>
                    </div>
                </div>


                <form
                    class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5 sm:p-6"
                    @submit.prevent="
                        addNewItem
                    "
                >


                    <div
                        v-if="activeTab === 'ict'"
                        class="rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3"
                    >
                        <p class="text-xs font-black text-blue-800">
                            ICT & Other Items
                        </p>
                        <p class="mt-1 text-[11px] font-semibold leading-5 text-blue-700/80">
                            Select the Inventory Year and applicable Quarter(s).
                            Stock monitoring and release fields remain for Supplies only.
                        </p>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-5 md:grid-cols-2"
                    >
                        <!-- ITEM -->
                        <div
                            class="md:col-span-2"
                        >
                            <label
                                class="mb-2 block text-sm font-black text-slate-800"
                            >
                                Item Name
                            </label>

                            <input
                                v-model="
                                    newItemForm.item
                                "
                                type="text"
                                placeholder="Enter item name"
                                class="h-11 w-full rounded-xl border bg-white px-4 text-sm font-semibold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                :class="
                                    addItemErrors.item
                                        ? 'border-rose-400'
                                        : 'border-slate-200 focus:border-blue-400'
                                "
                            />

                            <p
                                v-if="
                                    addItemErrors.item
                                "
                                class="mt-2 text-xs font-bold text-rose-600"
                            >
                                {{
                                    addItemErrors.item
                                }}
                            </p>
                        </div>


                        <!-- UNIT -->
                        <div>
                            <label
                                class="mb-2 block text-sm font-black text-slate-800"
                            >
                                Unit of Measure
                            </label>

                            <select
                                v-model="
                                    newItemForm.unit
                                "
                                class="h-11 w-full rounded-xl border bg-white px-3 text-sm font-bold text-slate-700 outline-none focus:ring-4 focus:ring-blue-100"
                                :class="
                                    addItemErrors.unit
                                        ? 'border-rose-400'
                                        : 'border-slate-200 focus:border-blue-400'
                                "
                            >
                                <option
                                    value=""
                                    disabled
                                >
                                    Select unit
                                </option>

                                <option
                                    v-for="
                                        unit in unitOptions
                                    "
                                    :key="
                                        `add-${unit.value}`
                                    "
                                    :value="
                                        unit.value
                                    "
                                >
                                    {{ unit.label }}
                                </option>
                            </select>

                            <p
                                v-if="
                                    addItemErrors.unit
                                "
                                class="mt-2 text-xs font-bold text-rose-600"
                            >
                                {{
                                    addItemErrors.unit
                                }}
                            </p>
                        </div>

                        <!-- INVENTORY YEAR -->
                        <div>
                            <label
                                class="mb-2 block text-sm font-black text-slate-800"
                            >
                                Inventory Year
                            </label>

                            <select
                                v-model.number="newItemForm.inventory_year"
                                class="h-11 w-full rounded-xl border bg-white px-3 text-sm font-bold text-slate-700 outline-none focus:ring-4 focus:ring-blue-100"
                                :class="
                                    addItemErrors.inventory_year
                                        ? 'border-rose-400'
                                        : 'border-slate-200 focus:border-blue-400'
                                "
                            >
                                <option
                                    v-for="year in yearOptions"
                                    :key="`add-year-${year}`"
                                    :value="year"
                                >
                                    {{ year }}
                                </option>
                            </select>

                            <p
                                v-if="addItemErrors.inventory_year"
                                class="mt-2 text-xs font-bold text-rose-600"
                            >
                                {{ addItemErrors.inventory_year }}
                            </p>
                        </div>

                        <!-- FIXED -->
                        <div v-if="activeTab === 'supplies'">
                            <label
                                class="mb-2 block text-sm font-black text-slate-800"
                            >
                                Fixed Value
                                <span class="font-semibold text-slate-400">(Optional)</span>
                            </label>

                            <input
                                v-model.number="
                                    newItemForm.fixed
                                "
                                type="number"
                                min="0"
                                step="1"
                                placeholder="Leave blank if baseline was not provided"
                                class="h-11 w-full rounded-xl border bg-white px-4 text-sm font-black text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                :class="
                                    addItemErrors.fixed
                                        ? 'border-rose-400'
                                        : 'border-slate-200 focus:border-blue-400'
                                "
                            />

                            <p
                                v-if="
                                    addItemErrors.fixed
                                "
                                class="mt-2 text-xs font-bold text-rose-600"
                            >
                                {{
                                    addItemErrors.fixed
                                }}
                            </p>

                            <p
                                v-else
                                class="mt-2 text-[10px] font-semibold text-slate-400"
                            >
                                Optional. If left blank, Quantity Released starts at 0 and accumulates every release made in the system.
                            </p>
                        </div>

                        <!-- CURRENTLY AVAILABLE -->
                        <div v-if="activeTab === 'supplies'">
                            <label
                                class="mb-2 block text-sm font-black text-slate-800"
                            >
                                Currently Available in SPD
                            </label>

                            <input
                                v-model.number="
                                    newItemForm.currently_available
                                "
                                type="number"
                                min="0"
                                :max="
                                    newItemForm.fixed !== ''
                                        ? newItemForm.fixed
                                        : undefined
                                "
                                step="1"
                                placeholder="Enter actual current stock"
                                class="h-11 w-full rounded-xl border bg-white px-4 text-sm font-semibold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                :class="
                                    addItemErrors.currently_available
                                        ? 'border-rose-400'
                                        : 'border-slate-200 focus:border-blue-400'
                                "
                            />

                            <p
                                v-if="addItemErrors.currently_available"
                                class="mt-2 text-xs font-bold text-rose-600"
                            >
                                {{
                                    addItemErrors.currently_available
                                }}
                            </p>

                            <div
                                class="mt-2 rounded-lg border border-blue-100 bg-blue-50/60 px-3 py-2"
                            >
                                <p
                                    class="text-[9px] font-black uppercase tracking-[0.12em] text-blue-500"
                                >
                                    Quantity Released · Auto
                                </p>

                                <p
                                    class="mt-0.5 text-sm font-black tabular-nums text-blue-700"
                                >
                                    {{
                                        newItemQuantityReleased
                                        ?? '—'
                                    }}
                                </p>

                                <p
                                    class="mt-0.5 text-[9px] font-semibold text-slate-400"
                                >
                                    {{
                                        newItemForm.fixed === ''
                                            || newItemForm.fixed === null
                                            || newItemForm.fixed === undefined
                                            || Number(newItemForm.fixed) <= 0
                                            ? 'Starts at 0 · accumulates releases made in the system'
                                            : 'Fixed Value − Currently Available'
                                    }}
                                </p>
                            </div>
                        </div>

                    </div>


                    <!-- QUARTERS -->
                    <div>
                        <div
                            class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <label
                                    class="block text-sm font-black text-slate-800"
                                >
                                    Applicable Quarter(s)
                                </label>

                                <p
                                    class="mt-1 text-xs font-semibold text-slate-500"
                                >
                                    Select the exact quarter(s)
                                    where this item belongs.
                                </p>
                            </div>

                            <span
                                class="text-xs font-black text-blue-600"
                            >
                                {{
                                    newItemForm
                                        .quarters
                                        .length
                                }}
                                selected
                            </span>
                        </div>

                        <div
                            class="grid grid-cols-2 gap-2 sm:grid-cols-5"
                        >
                            <label
                                class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-xs font-black text-slate-700"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        allNewItemQuartersSelected
                                    "
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    @change="
                                        toggleAllNewItemQuarters
                                    "
                                />

                                All
                            </label>

                            <label
                                v-for="
                                    quarter in quarterOptions.filter(
                                        (item) =>
                                            item.value !== 'all'
                                    )
                                "
                                :key="
                                    `new-item-${quarter.value}`
                                "
                                class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-3 text-xs font-black text-slate-700"
                            >
                                <input
                                    v-model="
                                        newItemForm.quarters
                                    "
                                    type="checkbox"
                                    :value="
                                        quarter.value
                                    "
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                />

                                {{
                                    quarter.label
                                }}
                            </label>
                        </div>

                        <p
                            v-if="
                                addItemErrors.quarters
                            "
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{
                                addItemErrors.quarters
                            }}
                        </p>
                    </div>


                    <!-- REMARKS -->
                    <div>
                        <label
                            class="mb-2 block text-sm font-black text-slate-800"
                        >
                            Remarks
                        </label>

                        <textarea
                            v-model="
                                newItemForm.remarks
                            "
                            rows="3"
                            placeholder="Optional remarks..."
                            class="w-full resize-y rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        ></textarea>
                    </div>


                    <div
                        class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end"
                    >
                        <button
                            type="button"
                            class="h-11 rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-600 hover:bg-slate-50"
                            @click="
                                closeAddItemModal
                            "
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="h-11 rounded-xl bg-blue-600 px-6 text-sm font-black text-white shadow-sm hover:bg-blue-700"
                        >
                            Add Item
                        </button>
                    </div>
                </form>
            </div>
        </div>


        <!-- FULL EDIT ITEM MODAL -->
        <div
            v-if="showFullEditModal && fullEditingItem"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
            @click.self="closeFullEditModal"
        >
            <div class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
                <div class="sticky top-0 z-10 border-b border-slate-200 bg-white px-5 py-5 sm:px-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.15em] text-blue-600">
                                Edit Inventory Item
                            </p>
                            <h3 class="mt-1 break-words text-xl font-black text-slate-900">
                                {{ fullEditingItem.item || 'Inventory Item' }}
                            </h3>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                Edit all item details here. Quantity Released remains automatic.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-lg font-bold text-slate-500 transition hover:bg-slate-50"
                            @click="closeFullEditModal"
                        >×</button>
                    </div>
                </div>

                <form
                    class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6"
                    @submit.prevent="saveFullEditItem"
                >
                    <div>
                        <label class="mb-2 block text-sm font-black text-slate-800">Category</label>
                        <select
                            v-model="fullEditForm.category"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        >
                            <option value="supplies">Supplies</option>
                            <option value="ict">ICT & Other Items</option>
                        </select>
                        <p v-if="fullEditErrors.category" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.category }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-black text-slate-800">Inventory Year</label>
                        <select
                            v-model.number="fullEditForm.inventory_year"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        >
                            <option v-for="year in yearOptions" :key="`edit-year-${year}`" :value="year">{{ year }}</option>
                        </select>
                        <p v-if="fullEditErrors.inventory_year" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.inventory_year }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-black text-slate-800">Item</label>
                        <input
                            v-model="fullEditForm.item"
                            type="text"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        />
                        <p v-if="fullEditErrors.item" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.item }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-black text-slate-800">Unit of Measure</label>
                        <input
                            v-model="fullEditForm.unit"
                            type="text"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold uppercase text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        />
                        <p v-if="fullEditErrors.unit" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.unit }}</p>
                    </div>

                    <template v-if="fullEditForm.category === 'supplies'">
                        <div>
                            <label class="mb-2 block text-sm font-black text-slate-800">
                                Fixed Value <span class="font-medium text-slate-400">(Optional)</span>
                            </label>
                            <input
                                v-model="fullEditForm.fixed_value"
                                type="number"
                                min="0"
                                step="1"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold tabular-nums text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            />
                            <p v-if="fullEditErrors.fixed_value" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.fixed_value }}</p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-black text-slate-800">Currently Available in SPD</label>
                            <input
                                v-model="fullEditForm.currently_available"
                                type="number"
                                min="0"
                                step="1"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold tabular-nums text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            />
                            <p v-if="fullEditErrors.currently_available" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.currently_available }}</p>
                        </div>
                    </template>

                    <div class="sm:col-span-2">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <label class="block text-sm font-black text-slate-800">Applicable Quarter(s)</label>
                            <button
                                type="button"
                                class="text-[10px] font-black text-blue-600 hover:text-blue-700"
                                @click="toggleAllFullEditQuarters"
                            >
                                {{ allFullEditQuartersSelected ? 'Clear All' : 'Select All' }}
                            </button>
                        </div>

                        <div class="grid grid-cols-4 gap-2">
                            <button
                                v-for="quarter in quarterValues"
                                :key="`full-edit-${quarter}`"
                                type="button"
                                class="rounded-xl border px-3 py-3 text-xs font-black transition"
                                :class="
                                    fullEditForm.quarters.includes(quarter)
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-blue-50 hover:text-blue-700'
                                "
                                @click="toggleFullEditQuarter(quarter)"
                            >
                                {{ quarter.toUpperCase() }}
                            </button>
                        </div>

                        <p v-if="fullEditErrors.quarters" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.quarters }}</p>
                    </div>

                

                    <div class="sm:col-span-2">
                        <label class="mb-2 block text-sm font-black text-slate-800">Remarks</label>
                        <textarea
                            v-model="fullEditForm.remarks"
                            rows="4"
                            class="w-full resize-y rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        ></textarea>
                        <p v-if="fullEditErrors.remarks" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.remarks }}</p>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:col-span-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="h-11 rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-600 transition hover:bg-slate-50"
                            @click="closeFullEditModal"
                        >Cancel</button>

                        <button
                            type="submit"
                            class="h-11 rounded-xl bg-blue-600 px-6 text-sm font-black text-white shadow-sm transition hover:bg-blue-700"
                        >Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RELEASE ITEM MODAL -->
        <div
            v-if="showReleaseItemModal"
            class="fixed inset-0 z-[65] flex items-end justify-center bg-slate-950/55 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            @click.self="closeReleaseItemModal"
        >
            <div
                class="w-full max-w-lg overflow-hidden rounded-t-[2rem] bg-white shadow-2xl sm:rounded-2xl"
            >
                <!-- HEADER -->
                <div
                    class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600"
                        >
                            Release Inventory
                        </p>

                        <h3
                            class="mt-1 break-words text-xl font-black text-slate-900"
                        >
                            {{
                                releasingItem?.item
                                || 'Inventory Item'
                            }}
                        </h3>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-2 text-[10px] font-bold text-slate-500"
                        >
                            <span
                                class="rounded-md bg-slate-100 px-2 py-1"
                            >
                                {{ releasingItem?.unit || '—' }}
                            </span>

                            <span>
                                Fixed Value:
                                <strong class="text-slate-800">
                                    {{ releaseHasFixedBaseline ? releaseFixedValue : '—' }}
                                </strong>
                            </span>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-lg font-bold text-slate-500 transition hover:bg-slate-50"
                        @click="closeReleaseItemModal"
                    >
                        ×
                    </button>
                </div>

                <!-- BODY -->
                <form
                    class="space-y-5 p-5 sm:p-6"
                    @submit.prevent="saveReleaseItem"
                >
                    <!-- CURRENT INVENTORY SUMMARY -->
                    <div
                        class="grid grid-cols-2 overflow-hidden rounded-xl border border-slate-200"
                    >
                        <div class="bg-blue-50/60 p-4">
                            <p
                                class="text-[9px] font-black uppercase tracking-[0.12em] text-blue-500"
                            >
                                Quantity Released
                            </p>

                            <p
                                class="mt-1 text-2xl font-black tabular-nums text-blue-700"
                            >
                                {{ releaseTotalReleased ?? '—' }}
                            </p>

                            <p
                                v-if="!releaseHasFixedBaseline"
                                class="mt-1 text-[9px] font-bold leading-3 text-slate-400"
                            >
                                System-tracked total · each release adds to this number.
                            </p>
                        </div>

                        <div
                            class="border-l border-slate-200 bg-emerald-50/50 p-4"
                        >
                            <p
                                class="text-[9px] font-black uppercase tracking-[0.12em] text-emerald-600"
                            >
                                Currently Available in SPD
                            </p>

                            <p
                                class="mt-1 text-2xl font-black tabular-nums text-emerald-700"
                            >
                                {{ releaseCurrentAvailable }}
                            </p>
                        </div>
                    </div>

                    <!-- QUANTITY TO RELEASE -->
                    <div>
                        <div
                            class="mb-2 flex items-end justify-between gap-3"
                        >
                            <label
                                class="block text-sm font-black text-slate-800"
                            >
                                Quantity to Release
                            </label>

                            <span
                                class="text-[10px] font-bold text-slate-400"
                            >
                                Max:
                                {{ releaseCurrentAvailable }}
                            </span>
                        </div>

                        <input
                            v-model="releaseItemForm.releaseQuantity"
                            type="number"
                            min="1"
                            :max="releaseCurrentAvailable"
                            step="1"
                            placeholder="Enter quantity to release"
                            class="h-12 w-full rounded-xl border bg-white px-4 text-base font-black tabular-nums text-slate-900 outline-none transition focus:ring-4 focus:ring-blue-100"
                            :class="
                                releaseItemErrors.releaseQuantity
                                    ? 'border-rose-400'
                                    : 'border-slate-200 focus:border-blue-400'
                            "
                        />

                        <p
                            v-if="releaseItemErrors.releaseQuantity"
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{
                                releaseItemErrors.releaseQuantity
                            }}
                        </p>
                    </div>

                    <!-- RELEASE PREVIEW -->
                    <div
                        v-if="releaseQuantity > 0"
                        class="rounded-xl border border-blue-100 bg-blue-50/40 p-4"
                    >
                        <p
                            class="text-[9px] font-black uppercase tracking-[0.12em] text-blue-500"
                        >
                            After This Release
                        </p>

                        <div
                            class="mt-3 grid grid-cols-2 gap-3"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-bold text-slate-500"
                                >
                                    Quantity Released
                                </p>

                                <p
                                    class="mt-1 text-lg font-black tabular-nums text-slate-900"
                                >
                                    {{
                                        releaseTotalReleasedAfter
                                        ?? '—'
                                    }}
                                </p>
                            </div>

                            <div>
                                <p
                                    class="text-[10px] font-bold text-slate-500"
                                >
                                    Currently Available
                                </p>

                                <p
                                    class="mt-1 text-lg font-black tabular-nums"
                                    :class="
                                        releaseRemainingQuantity <= 3
                                            ? 'text-rose-600'
                                            : 'text-emerald-700'
                                    "
                                >
                                    {{
                                        releaseRemainingQuantity
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- REMARKS -->
                    <div>
                        <label
                            class="mb-2 block text-sm font-black text-slate-800"
                        >
                            Remarks
                        </label>

                        <textarea
                            v-model="releaseItemForm.remarks"
                            rows="3"
                            placeholder="Optional remarks for this release..."
                            class="w-full resize-y rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        ></textarea>

                        <p
                            v-if="releaseItemErrors.remarks"
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{ releaseItemErrors.remarks }}
                        </p>
                    </div>

                    <!-- ACTIONS -->
                    <div
                        class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end"
                    >
                        <button
                            type="button"
                            class="h-11 rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-600 transition hover:bg-slate-50"
                            @click="closeReleaseItemModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="h-11 rounded-xl bg-blue-600 px-6 text-sm font-black text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="
                                releaseCurrentAvailable <= 0
                            "
                        >
                            Release Item
                        </button>
                    </div>
                </form>
            </div>
        </div>


        <!-- HISTORY MODAL -->
        <div
            v-if="showHistoryModal"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-[2px]"
            @click.self="closeHistoryModal"
        >
            <div
                class="flex max-h-[86vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            >
                <!-- HEADER -->
                <div
                    class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-600"
                        >
                            Inventory History
                        </p>

                        <h3
                            class="mt-1 break-words text-xl font-black text-slate-900"
                        >
                            {{
                                historyItem?.item
                                || 'Inventory Item'
                            }}
                        </h3>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] font-semibold text-slate-500"
                        >
                            <span>
                                Unit:
                                <strong class="text-slate-700">
                                    {{
                                        historyItem?.unit
                                        || '—'
                                    }}
                                </strong>
                            </span>

                            <span>
                                Currently Available:
                                <strong class="text-emerald-700">
                                    {{
                                        currentAvailableValue(
                                            historyItem
                                        )
                                        ?? '—'
                                    }}
                                </strong>
                            </span>

                            <span>
                                Quantity Released:
                                <strong class="text-blue-700">
                                    {{
                                        quantityReleasedValue(
                                            historyItem
                                        )
                                        ?? '—'
                                    }}
                                </strong>
                            </span>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-lg font-bold text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                        @click="closeHistoryModal"
                    >
                        ×
                    </button>
                </div>

                <!-- BODY -->
                <div
                    class="min-h-0 flex-1 overflow-y-auto"
                >
                    <!-- LOADING -->
                    <div
                        v-if="historyLoading"
                        class="flex min-h-52 flex-col items-center justify-center px-6 text-center"
                    >
                        <div
                            class="h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-blue-500"
                        ></div>

                        <p
                            class="mt-3 text-xs font-bold text-slate-500"
                        >
                            Loading history...
                        </p>
                    </div>

                    <!-- ERROR -->
                    <div
                        v-else-if="historyError"
                        class="m-5 rounded-xl border border-rose-200 bg-rose-50 p-4 sm:m-6"
                    >
                        <p
                            class="text-sm font-black text-rose-700"
                        >
                            Unable to load history
                        </p>

                        <p
                            class="mt-1 text-xs font-semibold text-rose-600"
                        >
                            {{ historyError }}
                        </p>
                    </div>

                    <!-- EMPTY -->
                    <div
                        v-else-if="!inventoryHistories.length"
                        class="flex min-h-60 flex-col items-center justify-center px-6 text-center"
                    >
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M20 6v5h-5" />
                                <path d="M19 11a7 7 0 1 0 1 4" />
                            </svg>
                        </div>

                        <p
                            class="mt-3 text-sm font-black text-slate-700"
                        >
                            No history yet
                        </p>

                        <p
                            class="mt-1 text-xs font-semibold text-slate-400"
                        >
                            Inventory updates will appear here.
                        </p>
                    </div>

                    <!-- CLEAN HISTORY LIST -->
                    <div v-else>
                        <article
                            v-for="history in inventoryHistories"
                            :key="`inventory-history-${history.id}`"
                            class="border-b border-slate-100 px-5 py-4 last:border-b-0 sm:px-6"
                        >
                            <!-- TOP ROW -->
                            <div
                                class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[11px] font-black text-blue-700"
                                    >
                                        {{
                                            String(
                                                history.updated_by_name
                                                || 'U'
                                            )
                                                .trim()
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </div>

                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-xs font-black text-slate-800"
                                        >
                                            {{
                                                history.updated_by_name
                                                || 'Unknown User'
                                            }}
                                        </p>

                                        <p
                                            class="text-[10px] font-semibold text-slate-400"
                                        >
                                            {{
                                                historyActionLabel(
                                                    history
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <span
                                    class="pl-10 text-[10px] font-bold text-slate-400 sm:pl-0"
                                >
                                    {{
                                        history.created_at
                                        || '—'
                                    }}
                                </span>
                            </div>

                            <!-- CHANGES -->
                            <div
                                class="mt-3 space-y-2 pl-10"
                            >
                                <!-- FULL FIELD-BY-FIELD AUDIT -->
                                <div
                                    v-if="historyHasDetailedChanges(history)"
                                    class="space-y-2"
                                >
                                    <div
                                        v-for="(change, changeIndex) in history.changes"
                                        :key="`history-${history.id}-change-${changeIndex}`"
                                        class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2"
                                    >
                                        <p
                                            class="text-[9px] font-black uppercase tracking-[0.10em] text-slate-400"
                                        >
                                            {{ change.label }}
                                        </p>

                                        <div
                                            v-if="change.single"
                                            class="mt-1 break-words text-xs font-black text-blue-700"
                                        >
                                            {{
                                                historyChangeValue(
                                                    change,
                                                    change.new
                                                )
                                            }}
                                        </div>

                                        <div
                                            v-else
                                            class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs"
                                        >
                                            <span
                                                class="break-words font-semibold text-slate-500"
                                            >
                                                {{
                                                    historyChangeValue(
                                                        change,
                                                        change.old
                                                    )
                                                }}
                                            </span>

                                            <span
                                                class="font-black text-slate-300"
                                            >
                                                →
                                            </span>

                                            <span
                                                class="break-words font-black text-blue-700"
                                            >
                                                {{
                                                    historyChangeValue(
                                                        change,
                                                        change.new
                                                    )
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- LEGACY HISTORY FALLBACK -->
                                <div
                                    v-if="
                                        !historyHasDetailedChanges(history)
                                        && historyReleasedChanged(history)
                                    "
                                    class="flex items-center gap-2 text-xs"
                                >
                                    <span
                                        class="font-semibold text-slate-500"
                                    >
                                        {{
                                            historyQuantityLabel(
                                                history
                                            )
                                        }}:
                                    </span>

                                    <span
                                        class="font-black tabular-nums text-blue-700"
                                    >
                                        {{
                                            historyQuantityDisplay(
                                                history
                                            )
                                        }}
                                    </span>
                                </div>

                                <div
                                    v-if="
                                        !historyHasDetailedChanges(history)
                                        && historyRemarksChanged(history)
                                    "
                                    class="text-xs"
                                >
                                    <span
                                        class="font-semibold text-slate-500"
                                    >
                                        Remarks:
                                    </span>

                                    <span
                                        class="ml-1 break-words font-semibold text-slate-700"
                                    >
                                        {{
                                            historyRemarksText(
                                                history
                                            )
                                        }}
                                    </span>
                                </div>

                                <p
                                    v-if="
                                        !historyHasDetailedChanges(history)
                                        && !historyReleasedChanged(history)
                                        && !historyRemarksChanged(history)
                                    "
                                    class="text-xs font-semibold text-slate-400"
                                >
                                    Inventory record updated.
                                </p>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- FOOTER -->
                <div
                    class="shrink-0 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:px-6"
                >
                    <button
                        type="button"
                        class="h-10 w-full rounded-xl bg-blue-500 text-xs font-black text-white transition hover:bg-blue-600"
                        @click="closeHistoryModal"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>


    </div>
</template>
