<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'
import DTSLayout from '@/Layouts/DTSLayout.vue'

defineOptions({
    layout: DTSLayout,
})

const props = defineProps({
    inventoryItems: {
        type: Array,
        default: () => [],
    },

    canManageInventory: {
        type: Boolean,
        default: false,
    },

    reconciliations: {
        type: Object,
        default: () => ({}),
    },

    mrPersonnel: {
        type: Array,
        default: () => [],
    },
})

const canManageInventory = computed(() =>
    Boolean(props.canManageInventory)
)

const activeTab = ref('supplies')
const search = ref('')
const yearFilter = ref(2026)
const unitFilter = ref('all')
const quarterFilter = ref('all')
const mrFilter = ref('all')
const currentPage = ref(1)

const mrPersonnelOptions = computed(() => {
    const seen = new Set()

    return (Array.isArray(props.mrPersonnel)
        ? props.mrPersonnel
        : []
    )
        .map((person) => ({
            id: String(
                person?.id
                ?? person?.ID
                ?? ''
            ).trim(),
            name: String(
                person?.name
                ?? ''
            ).trim(),
        }))
        .filter((person) => {
            if (
                !person.id
                || !person.name
                || seen.has(person.id)
            ) {
                return false
            }

            seen.add(person.id)
            return true
        })
        .sort((a, b) =>
            a.name.localeCompare(b.name)
        )
})

const mrPersonnelName = (personnelId) => {
    const id =
        String(personnelId ?? '').trim()

    return (
        mrPersonnelOptions.value.find(
            (person) => person.id === id
        )?.name
        || ''
    )
}

const mrPersonnelIdByName = (name) => {
    const normalized =
        String(name || '')
            .trim()
            .toLowerCase()

    if (!normalized) {
        return ''
    }

    return (
        mrPersonnelOptions.value.find(
            (person) =>
                person.name.toLowerCase()
                === normalized
        )?.id
        || ''
    )
}

const syncAssetMr = (asset) => {
    if (!asset) {
        return
    }

    const id =
        String(
            asset.mr_personnel_id
            ?? ''
        ).trim()

    asset.mr =
        id
            ? mrPersonnelName(id)
            : ''
}

const perPage = 8


const reconciliationSearch = ref('')
const reconciliationStatusFilter = ref('all')
const reconciliationReferenceCategory = ref('supplies')
const reconciliationReturnTab = ref('supplies')
const reconciliationReturnOtherCategory = ref('furniture_fixtures')
const reconciliationFileInput = ref(null)
const reconciliationUploading = ref(false)
const reconciliationUploadError = ref('')

const emptyReconciliationData = (category) => ({
    has_reference: false,
    reference: {
        reference_category: category,
        reference_category_label:
            {
                supplies: 'Supplies',
                ict: 'ICT',
                furniture_fixtures: 'Furniture/Fixtures',
                emergency_kits: 'Emergency Kits',
                token_giveaways: 'Token and Giveaways',
            }[category] || 'Inventory',
        original_name: null,
        uploaded_at: null,
        uploaded_by: null,
        row_count: 0,
    },
    summary: {
        total: 0,
        equal: 0,
        not_equal: 0,
        missing: 0,
    },
    rows: [],
    error: null,
})

const reconciliationData = computed(() => {
    const category =
        reconciliationReferenceCategory.value

    return (
        props.reconciliations?.[category]
        || emptyReconciliationData(
            category
        )
    )
})

const reconciliationRows = computed(() => {
    const rows =
        Array.isArray(
            reconciliationData.value.rows
        )
            ? reconciliationData.value.rows
            : []

    const term =
        String(
            reconciliationSearch.value || ''
        )
            .trim()
            .toLowerCase()

    return rows.filter((row) => {
        if (
            reconciliationStatusFilter.value !== 'all'
            && row.status
                !== reconciliationStatusFilter.value
        ) {
            return false
        }

        if (!term) {
            return true
        }

        const haystack = [
            row.category_label,
            row.item,
            row.property_number,
            row.website?.item,
            row.website?.description,
            row.website?.property_number,
            row.website?.current_user,
            row.website?.location,
            row.excel?.item,
            row.excel?.description,
            row.excel?.property_number,
            row.excel?.current_user,
            row.excel?.location,
        ]
            .map(
                (value) =>
                    String(value || '')
                        .toLowerCase()
            )
            .join(' ')

        return haystack.includes(term)
    })
})

const reconciliationStatusLabel = (status) => {
    return {
        equal: 'Equal',
        not_equal: 'Not Equal',
        missing_in_website:
            'Missing in Website',
        missing_in_excel:
            'Missing in Excel',
    }[status] || 'Needs Review'
}

const reconciliationStatusClass = (status) => {
    if (status === 'equal') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    }

    if (status === 'not_equal') {
        return 'border-rose-200 bg-rose-50 text-rose-700'
    }

    return 'border-amber-200 bg-amber-50 text-amber-700'
}

const reconciliationPrimaryLabel = (row) => {
    const item =
        String(row?.item || '').trim()

    const propertyNumber =
        String(
            row?.property_number || ''
        ).trim()

    if (
        item
        && propertyNumber
    ) {
        return `${item} · ${propertyNumber}`
    }

    return (
        item
        || propertyNumber
        || 'Inventory Record'
    )
}

const reconciliationDetailLines = (record) => {
    if (!record) {
        return []
    }

    const lines = []

    const push = (
        label,
        value,
        {
            blankLabel = '—',
        } = {}
    ) => {
        const text =
            String(value ?? '').trim()

        lines.push({
            label,
            value:
                text || blankLabel,
        })
    }

    if (
        String(
            record.description || ''
        ).trim()
    ) {
        push(
            'Description',
            record.description
        )
    }

    if (
        String(
            record.property_number || ''
        ).trim()
    ) {
        push(
            'Property Number',
            record.property_number
        )

        push(
            'Current User',
            record.current_user,
            {
                blankLabel:
                    'Unassigned',
            }
        )
    } else {
        if (
            String(
                record.unit || ''
            ).trim()
        ) {
            push(
                'Unit',
                record.unit
            )
        }

        if (
            String(
                record.inventory_year || ''
            ).trim()
        ) {
            push(
                'Year',
                record.inventory_year
            )
        }

        if (
            String(
                record.available || ''
            ).trim()
        ) {
            push(
                'Available / Count',
                record.available
            )
        }
    }

    if (
        String(
            record.location || ''
        ).trim()
    ) {
        push(
            'Location',
            record.location
        )
    }

    return lines
}

const reconciliationReferenceCategoryOptions = [
    { value: 'supplies', label: 'Supplies' },
    { value: 'ict', label: 'ICT' },
    { value: 'furniture_fixtures', label: 'Furniture/Fixtures' },
    { value: 'emergency_kits', label: 'Emergency Kits' },
    { value: 'token_giveaways', label: 'Token and Giveaways' },
]

const reconciliationReferenceCategoryLabel = (value) => {
    return reconciliationReferenceCategoryOptions.find(
        (option) => option.value === value
    )?.label || 'Inventory'
}

const reconciliationUploadedAt = computed(() => {
    const raw =
        reconciliationData.value
            ?.reference
            ?.uploaded_at

    if (!raw) {
        return '—'
    }

    const date = new Date(raw)

    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return String(raw)
    }

    return date.toLocaleString(
        'en-PH',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }
    )
})

const chooseReconciliationFile = () => {
    reconciliationUploadError.value = ''

    reconciliationFileInput.value?.click()
}

const uploadReconciliationReference = (event) => {
    const file =
        event?.target?.files?.[0]

    if (!file) {
        return
    }

    const extension =
        String(
            file.name
                .split('.')
                .pop()
                || ''
        ).toLowerCase()

    if (
        !['xlsx', 'csv'].includes(
            extension
        )
    ) {
        reconciliationUploadError.value =
            'Please select an XLSX or CSV file.'

        event.target.value = ''
        return
    }

    const formData =
        new FormData()

    formData.append(
        'reference_category',
        reconciliationReferenceCategory.value
    )

    formData.append(
        'reference_file',
        file
    )

    reconciliationUploading.value = true
    reconciliationUploadError.value = ''

    router.post(
        '/dts/inventory/reconciliation/reference',
        formData,
        {
            forceFormData: true,
            preserveScroll: true,

            onSuccess: () => {
                reconciliationStatusFilter.value =
                    'all'

                reconciliationSearch.value =
                    ''
            },

            onError: (errors) => {
                reconciliationUploadError.value =
                    errors?.reference_file
                    || 'Unable to save the reference file.'
            },

            onFinish: () => {
                reconciliationUploading.value =
                    false

                if (
                    reconciliationFileInput.value
                ) {
                    reconciliationFileInput.value.value =
                        ''
                }
            },
        }
    )
}


const CUSTOM_UNIT_STORAGE_KEY =
    'dts_inventory_custom_units_v1'

const customUnitValues = ref({
    supplies: [],
    ict: [],
})

const addUnitEditorOpen = ref(false)
const addUnitValue = ref('')
const addUnitError = ref('')
const normalizeCustomUnitValue = (value) => {
    return String(value || '')
        .trim()
        .replace(/\s+/g, ' ')
        .toUpperCase()
}

const loadCustomUnits = () => {
    try {
        const stored =
            localStorage.getItem(
                CUSTOM_UNIT_STORAGE_KEY
            )

        if (!stored) {
            return
        }

        const parsed = JSON.parse(stored)

        customUnitValues.value = {
            supplies: Array.isArray(
                parsed?.supplies
            )
                ? parsed.supplies
                    .map(
                        normalizeCustomUnitValue
                    )
                    .filter(Boolean)
                : [],

            ict: Array.isArray(
                parsed?.ict
            )
                ? parsed.ict
                    .map(
                        normalizeCustomUnitValue
                    )
                    .filter(Boolean)
                : [],
        }
    } catch (error) {
        customUnitValues.value = {
            supplies: [],
            ict: [],
        }
    }
}

const persistCustomUnits = () => {
    try {
        localStorage.setItem(
            CUSTOM_UNIT_STORAGE_KEY,
            JSON.stringify(
                customUnitValues.value
            )
        )
    } catch (error) {
        // Optional browser persistence only.
    }
}

onMounted(() => {
    loadCustomUnits()
})

watch(
    customUnitValues,
    () => {
        persistCustomUnits()
    },
    {
        deep: true,
    }
)


const showHistoryModal = ref(false)
const historyItem = ref(null)
const inventoryHistories = ref([])
const historyLoading = ref(false)
const historyError = ref('')
const historyPropertyNumber = ref('')
const historyPropertyDescription = ref('')

/*
|--------------------------------------------------------------------------
| DELETE ITEM
|--------------------------------------------------------------------------
*/
const showDeleteItemModal = ref(false)
const deletingItem = ref(null)
const deleteItemProcessing = ref(false)

const showReleaseItemModal = ref(false)
const releasingItem = ref(null)
const releaseItemErrors = ref({})

const releaseItemForm = ref({
    releaseQuantity: '',
    releasePropertyNumber: '',
    releaseDestination: '',
    releaseMrPersonnelId: '',
    remarks: '',
})

const showFullEditModal = ref(false)

const showAddOtherCountField = ref(false)
const otherCountToAdd = ref('')

const newOtherAssetForm = ref({
    description: '',
    property_number: '',
    current_user: '',
})

const resetAddOtherCount = () => {
    showAddOtherCountField.value = false
    otherCountToAdd.value = ''

    newOtherAssetForm.value = {
        description: '',
        property_number: '',
        current_user: '',
    }
}
const fullEditingItem = ref(null)
const fullEditErrors = ref({})
const fullEditForm = ref({
    category: 'supplies',
    item: '',
    description: '',
    location: '',
    unit: '',
    inventory_year: 2026,
    fixed_value: '',
    currently_available: '',
    quarters: [],
    quarter_stock: {},
    ict_assets: [],
    remarks: '',
})

const showAddIctUnitFields = ref(false)

const newIctAssetForm = ref({
    description: '',
    accessories: '',
    property_number: '',
    current_user: '',
    date_acquired: '',
    life_span_ended: '',
    status: 'working',
    mr_personnel_id: '',
    mr: '',
})

const resetNewIctAssetForm = () => {
    newIctAssetForm.value = {
        description: '',
        accessories: '',
        property_number: '',
        current_user: '',
        date_acquired: '',
        life_span_ended: '',
        status: 'working',
        mr_personnel_id: '',
        mr: '',
    }

    showAddIctUnitFields.value = false
}

const showIctAssetEditModal = ref(false)
const ictAssetEditingItem = ref(null)
const ictAssetEditingIndex = ref(null)
const ictAssetEditingOriginalPropertyNumber = ref('')
const ictAssetEditErrors = ref({})
const ictAssetEditProcessing = ref(false)

const ictAssetEditForm = ref({
    description: '',
    accessories: '',
    property_number: '',
    current_user: '',
    date_acquired: '',
    life_span_ended: '',
    status: 'working',
    mr_personnel_id: '',
    mr: '',
})

const showAddItemModal = ref(false)
const addItemErrors = ref({})

const newItemForm = ref({
    item: '',
    description: '',
    other_category: 'furniture_fixtures',
    location: '',
    unit: '',
    inventory_year: 2026,
    fixed: '',
    currently_available: '',
    quarters: [],
    quarter_stock: {},
    ict_assets: [],
    remarks: '',
})

const expandedIctItems = ref({})

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

const normalizeQuarterStock = (value) => {
    let source = value

    if (
        typeof source === 'string'
        && source.trim()
    ) {
        try {
            source = JSON.parse(source)
        } catch (error) {
            source = {}
        }
    }

    if (
        !source
        || Array.isArray(source)
        || typeof source !== 'object'
    ) {
        return {}
    }

    const normalized = {}

    quarterValues.forEach((quarter) => {
        const entry = source?.[quarter]

        if (
            !entry
            || typeof entry !== 'object'
        ) {
            return
        }

        const current =
            Number(entry.current)

        const released =
            Number(entry.released ?? 0)

        const opening =
            Number(
                entry.opening
                ?? (
                    Number.isFinite(current)
                        ? current
                        + (
                            Number.isFinite(released)
                                ? released
                                : 0
                        )
                        : 0
                )
            )

        if (
            !Number.isFinite(current)
            || current < 0
        ) {
            return
        }

        const normalizedOpening =
            Number.isFinite(opening)
                ? Math.max(0, opening)
                : current

        const carryover =
            Number(entry.carryover ?? 0)

        const normalizedCarryover =
            Number.isFinite(carryover)
                ? Math.max(0, carryover)
                : 0

        const added =
            Number(
                entry.added
                ?? Math.max(
                    0,
                    normalizedOpening
                    - normalizedCarryover
                )
            )

        normalized[quarter] = {
            opening:
                normalizedOpening,
            carryover:
                normalizedCarryover,
            added:
                Number.isFinite(added)
                    ? Math.max(0, added)
                    : 0,
            current:
                Math.max(0, current),
            released:
                Number.isFinite(released)
                    ? Math.max(0, released)
                    : 0,
            remarks:
                String(
                    entry.remarks
                    ?? ''
                ).trim(),
        }
    })

    /*
     * Repair the carryover chain for ACTIVE quarters only.
     *
     * A completely zero quarter is considered a FUTURE / DORMANT
     * quarter. It must not become active merely because the item is
     * assigned to All Quarters.
     *
     * Example:
     * Q1 current = 10
     * Q2 stored as added 25 / carryover 0
     *
     * Normalized Q2:
     * carryover = 10
     * opening = 35
     * current = 35 - Q2 released
     */
    let previousCurrent = 0
    let hasPreviousActive = false

    quarterValues.forEach((quarter) => {
        const entry = normalized[quarter]

        if (!entry) {
            return
        }

        if (!quarterEntryHasActivity(entry)) {
            /*
             * Future quarter. Keep it dormant at zero and DO NOT
             * advance previousCurrent.
             */
            const remarks =
                String(
                    entry.remarks
                    ?? ''
                ).trim()

            entry.opening = 0
            entry.carryover = 0
            entry.added = 0
            entry.current = 0
            entry.released = 0
            entry.remarks = remarks
            return
        }

        const released =
            Math.max(
                0,
                Number(entry.released ?? 0)
            )

        const storedCarryover =
            Math.max(
                0,
                Number(entry.carryover ?? 0)
            )

        const storedOpening =
            Math.max(
                0,
                Number(entry.opening ?? 0)
            )

        const storedAdded =
            Math.max(
                0,
                Number(
                    entry.added
                    ?? Math.max(
                        0,
                        storedOpening
                        - storedCarryover
                    )
                )
            )

        const carryover =
            hasPreviousActive
                ? previousCurrent
                : 0

        const opening =
            carryover
            + storedAdded

        const current =
            Math.max(
                0,
                opening - released
            )

        entry.carryover = carryover
        entry.added = storedAdded
        entry.opening = opening
        entry.current = current
        entry.released = released

        previousCurrent = current
        hasPreviousActive = true
    })

    return normalized
}

const hasQuarterStock = (item) => {
    const category =
        String(item?.category || '')
            .trim()
            .toLowerCase()

    if (category !== 'supplies') {
        return false
    }

    return (
        Object.keys(
            normalizeQuarterStock(
                item?.quarter_stock
            )
        ).length > 0
    )
}

const quarterEntryHasActivity = (entry) => {
    if (!entry || typeof entry !== 'object') {
        return false
    }

    return [
        'opening',
        'carryover',
        'added',
        'current',
        'released',
    ].some((field) => {
        const value = Number(entry?.[field] ?? 0)

        return (
            Number.isFinite(value)
            && value > 0
        )
    })
}

const sortedQuarterKeys = (quarters) => {
    return [...new Set(
        (quarters || [])
            .map(
                (quarter) =>
                    String(quarter || '')
                        .trim()
                        .toLowerCase()
            )
            .filter(
                (quarter) =>
                    quarterValues.includes(quarter)
            )
    )].sort(
        (a, b) =>
            quarterValues.indexOf(a)
            - quarterValues.indexOf(b)
    )
}

const latestQuarterForItem = (item) => {
    const stock =
        normalizeQuarterStock(
            item?.quarter_stock
        )

    const activeStockQuarters =
        sortedQuarterKeys(
            Object.keys(stock)
                .filter(
                    (quarter) =>
                        quarterEntryHasActivity(
                            stock[quarter]
                        )
                )
        )

    if (activeStockQuarters.length) {
        return activeStockQuarters[
            activeStockQuarters.length - 1
        ]
    }

    const assigned =
        sortedQuarterKeys(
            inferredItemQuarters(item)
        )

    /*
     * If quarter_stock exists but all future quarters are still zero,
     * use the earliest assigned quarter as the initial active quarter.
     * This prevents All Quarters from jumping straight to Q4.
     */
    if (
        Object.keys(stock).length
        && assigned.length
    ) {
        return assigned[0]
    }

    return assigned.length
        ? assigned[
            assigned.length - 1
        ]
        : ''
}

const visibleQuarterBalanceEntries = (item) => {
    const stock =
        normalizeQuarterStock(
            item?.quarter_stock
        )

    let quarters =
        sortedQuarterKeys(
            Object.keys(stock)
                .filter(
                    (quarter) =>
                        quarterEntryHasActivity(
                            stock[quarter]
                        )
                )
        )

    /*
     * Legacy one-quarter fallback so the user can still see the
     * remaining balance before opening Edit.
     */
    if (!quarters.length) {
        const inferred =
            sortedQuarterKeys(
                inferredItemQuarters(item)
            )

        if (inferred.length === 1) {
            return [
                {
                    quarter:
                        inferred[0],
                    opening:
                        Number(
                            currentAvailableValue(
                                item,
                                inferred[0]
                            )
                            ?? 0
                        )
                        + Number(
                            quantityReleasedValue(
                                item,
                                inferred[0]
                            )
                            ?? 0
                        ),
                    carryover: 0,
                    added:
                        Number(
                            currentAvailableValue(
                                item,
                                inferred[0]
                            )
                            ?? 0
                        )
                        + Number(
                            quantityReleasedValue(
                                item,
                                inferred[0]
                            )
                            ?? 0
                        ),
                    current:
                        Number(
                            currentAvailableValue(
                                item,
                                inferred[0]
                            )
                            ?? 0
                        ),
                    released:
                        Number(
                            quantityReleasedValue(
                                item,
                                inferred[0]
                            )
                            ?? 0
                        ),
                },
            ]
        }

        return []
    }

    if (
        quarterFilter.value !== 'all'
        && quarters.includes(
            quarterFilter.value
        )
    ) {
        quarters = [
            quarterFilter.value,
        ]
    }

    return quarters.map(
        (quarter) => ({
            quarter,
            ...stock[quarter],
        })
    )
}

const previousSelectedQuarter = (
    quarters,
    quarter
) => {
    const sorted =
        sortedQuarterKeys(quarters)

    const index =
        sorted.indexOf(quarter)

    return index > 0
        ? sorted[index - 1]
        : ''
}

const originalEditQuarterStock =
    computed(() =>
        normalizeQuarterStock(
            fullEditingItem.value
                ?.quarter_stock
        )
    )

const isEditQuarterNew = (quarter) => {
    const entry =
        originalEditQuarterStock.value?.[
            quarter
        ]

    return (
        !entry
        || !quarterEntryHasActivity(entry)
    )
}

const projectedFormQuarterCurrent = (
    form,
    quarter,
    existingStock = {}
) => {
    const quarters =
        sortedQuarterKeys(
            form?.quarters
        )

    let runningCurrent = 0

    for (const value of quarters) {
        const raw =
            form?.quarter_stock?.[value]
                ?.current

        const numeric =
            Number(raw)

        if (
            existingStock?.[value]
            && quarterEntryHasActivity(
                existingStock[value]
            )
        ) {
            runningCurrent =
                Number.isFinite(numeric)
                    ? Math.max(0, numeric)
                    : Number(
                        existingStock[value]
                            .current
                        ?? 0
                    )
        } else {
            const added =
                Number.isFinite(numeric)
                    ? Math.max(0, numeric)
                    : 0

            runningCurrent +=
                added
        }

        if (value === quarter) {
            return runningCurrent
        }
    }

    return 0
}

const projectedQuarterCarryover = (
    form,
    quarter,
    existingStock = {}
) => {
    const previous =
        previousSelectedQuarter(
            form?.quarters,
            quarter
        )

    if (!previous) {
        return 0
    }

    return projectedFormQuarterCurrent(
        form,
        previous,
        existingStock
    )
}

const quarterStockValue = (
    item,
    field,
    selectedQuarter = quarterFilter.value
) => {
    const stock =
        normalizeQuarterStock(
            item?.quarter_stock
        )

    const stockQuarters =
        Object.keys(stock)
            .filter(
                (quarter) =>
                    quarterEntryHasActivity(
                        stock[quarter]
                    )
            )

    if (!stockQuarters.length) {
        return null
    }

    if (
        selectedQuarter !== 'all'
        && quarterValues.includes(
            selectedQuarter
        )
    ) {
        const value =
            Number(
                stock?.[selectedQuarter]?.[field]
            )

        return Number.isFinite(value)
            ? Math.max(0, value)
            : 0
    }

    const ordered =
        sortedQuarterKeys(
            stockQuarters
        )

    /*
     * Carryover means historical quarter balances are snapshots.
     * Do NOT sum "current" across quarters or the carryover is
     * double-counted. All Quarters uses the latest quarter's
     * physical current balance.
     */
    if (
        ['current', 'opening'].includes(
            field
        )
    ) {
        const latest =
            ordered[
                ordered.length - 1
            ]

        const value =
            Number(
                stock?.[latest]?.[field]
            )

        return Number.isFinite(value)
            ? Math.max(0, value)
            : 0
    }

    return ordered.reduce(
        (total, quarter) => {
            const value =
                Number(
                    stock?.[quarter]?.[field]
                )

            return total
                + (
                    Number.isFinite(value)
                        ? Math.max(0, value)
                        : 0
                )
        },
        0
    )
}

const quarterStockFormCurrent = (
    form,
    quarter
) => {
    return form?.quarter_stock?.[quarter]?.current
        ?? ''
}

const quarterStockFormRemarks = (
    form,
    quarter
) => {
    return String(
        form?.quarter_stock?.[quarter]?.remarks
        ?? ''
    )
}

const setNewQuarterStockRemarks = (
    quarter,
    value
) => {
    newItemForm.value.quarter_stock = {
        ...(newItemForm.value.quarter_stock || {}),
        [quarter]: {
            ...(
                newItemForm.value
                    .quarter_stock?.[quarter]
                || {}
            ),
            remarks:
                String(value ?? ''),
        },
    }
}

const setEditQuarterStockRemarks = (
    quarter,
    value
) => {
    fullEditForm.value.quarter_stock = {
        ...(fullEditForm.value.quarter_stock || {}),
        [quarter]: {
            ...(
                fullEditForm.value
                    .quarter_stock?.[quarter]
                || {}
            ),
            remarks:
                String(value ?? ''),
        },
    }
}

const quarterRemarksEntries = (
    item,
    selectedQuarter = quarterFilter.value
) => {
    const stock =
        normalizeQuarterStock(
            item?.quarter_stock
        )

    let quarters =
        inferredItemQuarters(item)

    if (
        selectedQuarter !== 'all'
        && quarterValues.includes(
            selectedQuarter
        )
    ) {
        quarters =
            quarters.includes(selectedQuarter)
                ? [selectedQuarter]
                : []
    }

    return sortedQuarterKeys(quarters)
        .map((quarter) => ({
            quarter,
            remarks:
                String(
                    stock?.[quarter]?.remarks
                    ?? ''
                ).trim(),
        }))
        .filter(
            (entry) =>
                entry.remarks !== ''
        )
}

const hasQuarterRemarks = (
    item,
    selectedQuarter = quarterFilter.value
) =>
    quarterRemarksEntries(
        item,
        selectedQuarter
    ).length > 0


const setNewQuarterStockCurrent = (
    quarter,
    value
) => {
    newItemForm.value.quarter_stock = {
        ...(newItemForm.value.quarter_stock || {}),
        [quarter]: {
            ...(
                newItemForm.value
                    .quarter_stock?.[quarter]
                || {}
            ),
            current: value,
        },
    }
}

const setEditQuarterStockCurrent = (
    quarter,
    value
) => {
    const originalEntry =
        originalEditQuarterStock.value?.[
            quarter
        ]

    const needsActivation =
        !originalEntry
        || !quarterEntryHasActivity(
            originalEntry
        )

    fullEditForm.value.quarter_stock = {
        ...(fullEditForm.value.quarter_stock || {}),
        [quarter]: {
            ...(
                fullEditForm.value
                    .quarter_stock?.[quarter]
                || {}
            ),
            current: value,
            ...(needsActivation
                ? { activate: true }
                : {}
            ),
        },
    }
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
            String(item.category || '')
                .trim()
                .toLowerCase() === 'supplies'
                    ? normalizeQuarters(
                        item.quarters
                    )
                    : [],
        quarter_stock:
            String(item.category || '')
                .trim()
                .toLowerCase() === 'supplies'
                    ? normalizeQuarterStock(
                        item.quarter_stock
                    )
                    : {},
        location:
            String(item.location ?? '').trim(),
        ict_assets:
            (
                String(item.category || '')
                    .trim()
                    .toLowerCase() === 'ict'
                || isPropertyTrackedOtherCategory(
                    item.category
                )
            )
                ? normalizeIctAssets(
                    item.ict_assets
                )
                : [],
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
 * Furniture and Fixtures are presented as ONE category in the UI.
 *
 * Existing database rows may still contain either:
 * - furniture
 * - fixtures
 *
 * We intentionally keep those legacy DB values valid so no existing
 * inventory record needs to be migrated or rewritten.
 */
const otherCategoryOptions = [
    {
        value: 'furniture_fixtures',
        label: 'Furniture/Fixtures',
    },
    {
        value: 'emergency_kits',
        label: 'Emergency Kits',
    },
    {
        value: 'token_giveaways',
        label: 'Token and Giveaways',
    },
]

/*
 * Actual Other Items category values accepted/stored in the database.
 */
const otherCategoryValues = [
    'furniture',
    'fixtures',
    'emergency_kits',
    'token_giveaways',
]

const otherCategoryFilterValues =
    otherCategoryOptions.map(
        (option) => option.value
    )

const furnitureFixtureCategories = [
    'furniture',
    'fixtures',
]

const propertyTrackedOtherCategories = [
    'furniture_fixtures',
    ...furnitureFixtureCategories,
]

const isPropertyTrackedOtherCategory = (category) =>
    propertyTrackedOtherCategories.includes(
        String(category || '')
            .trim()
            .toLowerCase()
    )

const otherFilterValueForCategory = (category) => {
    const normalized =
        String(category || '')
            .trim()
            .toLowerCase()

    return furnitureFixtureCategories.includes(
        normalized
    )
        ? 'furniture_fixtures'
        : normalized
}

/*
 * New records created from the merged Furniture/Fixtures view use
 * "furniture" internally. Existing "fixtures" rows remain untouched.
 */
const otherCategoryForSave = (filterValue) => {
    const normalized =
        String(filterValue || '')
            .trim()
            .toLowerCase()

    return normalized === 'furniture_fixtures'
        ? 'furniture'
        : normalized
}

const otherCategoryFilter = ref(
    'furniture_fixtures'
)

const otherItems = computed(() =>
    normalizedInventoryItems.value.filter(
        (item) =>
            otherCategoryValues.includes(
                item.category
            )
    )
)

/*
|--------------------------------------------------------------------------
| CURRENT DATA
|--------------------------------------------------------------------------
*/

const currentItems = computed(() => {
    if (activeTab.value === 'supplies') {
        return suppliesItems.value
    }

    if (
        activeTab.value === 'ict'
        || activeTab.value === 'returned'
    ) {
        return ictItems.value
    }

    const selectedFilter =
        otherCategoryFilter.value

    if (
        selectedFilter
        === 'furniture_fixtures'
    ) {
        return otherItems.value.filter(
            (item) =>
                furnitureFixtureCategories.includes(
                    item.category
                )
        )
    }

    return otherItems.value.filter(
        (item) =>
            item.category
            === selectedFilter
    )
})

const currentOtherCategoryLabel = computed(() => {
    return otherCategoryOptions.find(
        (option) =>
            option.value
            === otherCategoryFilter.value
    )?.label || 'Other Items'
})


const otherCategoryHasCount = (category) => {
    return otherCategoryValues.includes(
        category
    )
}

const otherCategoryCanRelease = (category) => {
    return otherCategoryValues.includes(
        category
    )
}

const currentOtherCategoryHasCount = computed(() => {
    if (
        otherCategoryFilter.value
        === 'furniture_fixtures'
    ) {
        return true
    }

    return otherCategoryHasCount(
        otherCategoryFilter.value
    )
})

const currentOtherCategoryCanRelease = computed(() => {
    if (
        otherCategoryFilter.value
        === 'furniture_fixtures'
    ) {
        return true
    }

    return otherCategoryCanRelease(
        otherCategoryFilter.value
    )
})

const currentOtherCategoryIsAssetTracked = computed(() =>
    isPropertyTrackedOtherCategory(
        otherCategoryFilter.value
    )
)

/*
 * Add Item modal can select a different Other Items category
 * without first switching the visible category tab.
 */
const addOtherCategoryLabel = computed(() => {
    return otherCategoryOptions.find(
        (option) =>
            option.value
            === newItemForm.value.other_category
    )?.label || 'Other Items'
})

const addOtherCategoryHasCount = computed(() => {
    const selected =
        newItemForm.value.other_category

    return (
        selected === 'furniture_fixtures'
        || otherCategoryHasCount(selected)
    )
})

const addOtherCategoryIsAssetTracked = computed(() =>
    isPropertyTrackedOtherCategory(
        newItemForm.value.other_category
    )
)


const ictDurationUnit = (itemOrUnit) => {
    const unit =
        typeof itemOrUnit === 'string'
            ? itemOrUnit
            : itemOrUnit?.unit

    const normalized =
        String(unit || '')
            .trim()
            .toUpperCase()

    return ['MONTH', 'YEAR'].includes(normalized)
        ? normalized
        : null
}

const isIctYearBased = (itemOrUnit) =>
    ictDurationUnit(itemOrUnit) === 'YEAR'

const isIctMonthBased = (itemOrUnit) =>
    ictDurationUnit(itemOrUnit) === 'MONTH'

const isIctSubscription = (itemOrUnit) =>
    Boolean(ictDurationUnit(itemOrUnit))

const ictAssetStatusOptions = [
    {
        value: 'working',
        label: 'Working',
    },
    {
        value: 'returned',
        label: 'Returned',
    },
]

const normalizeIctAssetStatus = (value) => {
    const normalized =
        String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[\s-]+/g, '_')

    if (normalized === 'for_return') {
        return 'returned'
    }

    return ['working', 'returned'].includes(
        normalized
    )
        ? normalized
        : ''
}

const ictAssetStatusLabel = (value) => {
    return (
        ictAssetStatusOptions.find(
            (option) =>
                option.value
                === normalizeIctAssetStatus(value)
        )?.label
        || 'Working'
    )
}

const ictAssetStatusClass = (value) => {
    return normalizeIctAssetStatus(value)
        === 'returned'
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : 'border-emerald-200 bg-emerald-50 text-emerald-700'
}

const ictDateToLocalMidnight = (value) => {
    const normalized =
        normalizeIctDateInput(value)

    if (!normalized) {
        return null
    }

    const [year, month, day] =
        normalized.split('-').map(Number)

    const date =
        new Date(year, month - 1, day)

    if (Number.isNaN(date.getTime())) {
        return null
    }

    date.setHours(0, 0, 0, 0)
    return date
}

const ictLifeSpanState = (asset) => {
    if (
        normalizeIctAssetStatus(
            asset?.status
        ) === 'returned'
    ) {
        return null
    }

    const endDate =
        ictDateToLocalMidnight(
            asset?.life_span_ended
        )

    if (!endDate) {
        return null
    }

    const today = new Date()
    today.setHours(0, 0, 0, 0)

    const dayMs =
        24 * 60 * 60 * 1000

    const daysRemaining =
        Math.ceil(
            (
                endDate.getTime()
                - today.getTime()
            ) / dayMs
        )

    const displayDate =
        normalizeIctDateInput(
            asset?.life_span_ended
        )

    if (daysRemaining <= 0) {
        return {
            level: 'ended',
            label: 'Life Span Ended',
            title:
                `Life span ended on ${displayDate}. Review this ICT unit for return.`,
        }
    }

    if (daysRemaining <= 365) {
        return {
            level: 'near',
            label: 'Near For Return',
            title:
                `${daysRemaining} day(s) remaining before life span ends on ${displayDate}.`,
        }
    }

    return null
}

const ictAssetNeedsLifeSpanAttention = (asset) =>
    Boolean(ictLifeSpanState(asset))

const ictLifeSpanRowClass = (asset) => {
    const warning =
        ictLifeSpanState(asset)

    if (!warning) {
        return 'bg-white hover:bg-blue-50/40'
    }

    if (warning.level === 'ended') {
        return 'bg-rose-100/90 hover:bg-rose-100 ring-1 ring-inset ring-rose-300'
    }

    return 'bg-rose-50/90 hover:bg-rose-100/70 ring-1 ring-inset ring-rose-200'
}

const ictLifeSpanBadgeClass = (asset) => {
    return ictLifeSpanState(asset)?.level
        === 'ended'
        ? 'border-rose-300 bg-rose-100 text-rose-800'
        : 'border-rose-200 bg-white text-rose-700'
}

const normalizeIctDateInput = (value) => {
    const raw =
        String(value ?? '').trim()

    if (!raw) {
        return ''
    }

    if (/^\d{4}$/.test(raw)) {
        return `${raw}-12-31`
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        return raw
    }

    return ''
}

const isValidIctDateRange = (
    dateAcquired,
    lifeSpanEnded
) => {
    const acquired =
        normalizeIctDateInput(dateAcquired)

    const ended =
        normalizeIctDateInput(lifeSpanEnded)

    if (!acquired || !ended) {
        return true
    }

    return ended >= acquired
}

const ictEditorAssetRows = (assets) => {
    if (!Array.isArray(assets)) {
        return []
    }

    return assets.map((asset) => ({
        description: String(
            asset?.description ?? ''
        ).trim(),
        accessories: String(
            asset?.accessories ?? ''
        ).trim(),
        property_number: String(
            asset?.property_number ?? ''
        ).trim(),
        current_user: String(
            asset?.current_user ?? ''
        ).trim(),
        date_acquired:
            normalizeIctDateInput(
                asset?.date_acquired
            ),
        life_span_ended:
            normalizeIctDateInput(
                asset?.life_span_ended
            ),
        status:
            normalizeIctAssetStatus(
                asset?.status
            ),
        mr_personnel_id:
            String(
                asset?.mr_personnel_id
                ?? mrPersonnelIdByName(
                    asset?.mr
                )
                ?? ''
            ).trim(),
        mr: String(
            asset?.mr ?? ''
        ).trim(),
    }))
}

const normalizeIctAssets = (assets) => {
    return ictEditorAssetRows(assets)
        .filter(
            (asset) =>
                asset.description
                || asset.accessories
                || asset.property_number
                || asset.current_user
                || asset.date_acquired
                || asset.life_span_ended
                || asset.status
                || asset.mr_personnel_id
                || asset.mr
        )
}

const ictEquipmentCount = (value) => {
    const number = Number(value)

    return (
        Number.isInteger(number)
        && number > 0
    )
        ? number
        : 0
}

const syncIctAssetRowsToCount = (
    assets,
    count
) => {
    const target =
        ictEquipmentCount(count)

    const rows =
        ictEditorAssetRows(assets)
            .slice(0, target)

    while (rows.length < target) {
        rows.push({
            description: '',
            accessories: '',
            property_number: '',
            current_user: '',
            date_acquired: '',
            life_span_ended: '',
            status: '',
            mr_personnel_id: '',
            mr: '',
        })
    }

    return rows
}

const ictAssetDetails = (item) => {
    const category =
        String(item?.category || '')
            .trim()
            .toLowerCase()

    const usesPropertyTracking =
        (
            category === 'ict'
            && !isIctSubscription(item)
        )
        || isPropertyTrackedOtherCategory(
            category
        )

    if (!usesPropertyTracking) {
        return []
    }

    return syncIctAssetRowsToCount(
        item?.ict_assets,
        item?.currently_available
    ).map((asset, index) => ({
        ...asset,
        _index: index,
    }))
}

const filteredIctAssetDetails = (item) => {
    let rows = ictAssetDetails(item)

    if (activeTab.value === 'ict') {
        rows = rows.filter(
            (asset) =>
                normalizeIctAssetStatus(
                    asset?.status
                ) !== 'returned'
        )
    }

    if (activeTab.value === 'returned') {
        rows = rows.filter(
            (asset) =>
                normalizeIctAssetStatus(
                    asset?.status
                ) === 'returned'
        )
    }

    if (
        !['ict', 'returned'].includes(
            activeTab.value
        )
        || mrFilter.value === 'all'
    ) {
        return rows
    }

    const selectedId =
        String(mrFilter.value || '').trim()

    const selectedName =
        mrPersonnelName(selectedId)
            .toLowerCase()

    return rows.filter((asset) => {
        const assetId =
            String(
                asset?.mr_personnel_id
                ?? ''
            ).trim()

        const assetName =
            String(asset?.mr || '')
                .trim()
                .toLowerCase()

        return (
            assetId === selectedId
            || (
                !assetId
                && selectedName
                && assetName === selectedName
            )
        )
    })
}

const visibleIctAssetDetails = (item) =>
    filteredIctAssetDetails(item)

const hasVisibleIctAssetDetails = (item) =>
    visibleIctAssetDetails(item).length > 0

const hasIctAssetDetails = (item) =>
    ictAssetDetails(item).length > 0

const selectedMrName = computed(() =>
    mrFilter.value === 'all'
        ? ''
        : mrPersonnelName(
            mrFilter.value
        )
)

const returnedAssetRows = computed(() => {
    const rows = []

    for (const item of ictItems.value) {
        for (const asset of ictAssetDetails(item)) {
            if (
                normalizeIctAssetStatus(
                    asset?.status
                ) !== 'returned'
            ) {
                continue
            }

            rows.push({
                key:
                    `${item.id}-${asset._index}-${asset.property_number || 'returned'}`,
                item,
                asset,
            })
        }
    }

    return rows
})

const returnedAssetCount = computed(
    () => returnedAssetRows.value.length
)

const filteredReturnedAssetRows = computed(() => {
    const term =
        String(search.value || '')
            .trim()
            .toLowerCase()

    const selectedMrId =
        String(mrFilter.value || '').trim()

    const selectedMrName =
        mrPersonnelName(selectedMrId)
            .trim()
            .toLowerCase()

    return returnedAssetRows.value.filter(
        ({ item, asset }) => {
            const matchesYear =
                Number(item.inventory_year)
                === Number(yearFilter.value)

            const matchesUnit =
                unitFilter.value === 'all'
                || String(item.unit || '')
                    === String(unitFilter.value)

            const assetMrId =
                String(
                    asset?.mr_personnel_id
                    ?? ''
                ).trim()

            const assetMrName =
                String(asset?.mr || '')
                    .trim()
                    .toLowerCase()

            const matchesMr =
                mrFilter.value === 'all'
                || assetMrId === selectedMrId
                || (
                    !assetMrId
                    && selectedMrName
                    && assetMrName
                        === selectedMrName
                )

            const searchableValues = [
                item.item,
                item.unit,
                item.inventory_year,
                item.remarks,
                asset.description,
                asset.accessories,
                asset.property_number,
                asset.current_user,
                asset.mr,
                'Returned',
            ]

            const matchesSearch =
                !term
                || searchableValues.some(
                    (value) =>
                        String(value || '')
                            .toLowerCase()
                            .includes(term)
                )

            return (
                matchesYear
                && matchesUnit
                && matchesMr
                && matchesSearch
            )
        }
    )
})

const filteredMrAssetCount = computed(() => {
    if (
        !['ict', 'returned'].includes(
            activeTab.value
        )
        || mrFilter.value === 'all'
    ) {
        return 0
    }

    if (activeTab.value === 'returned') {
        return filteredReturnedAssetRows.value.length
    }

    return filteredItems.value.reduce(
        (total, item) =>
            total
            + filteredIctAssetDetails(item).length,
        0
    )
})

const isIctExpanded = (item) =>
    Boolean(
        expandedIctItems.value[
            Number(item?.id)
        ]
    )

const toggleIctAssetDetails = (item) => {
    if (!hasIctAssetDetails(item)) {
        return
    }

    const key = Number(item.id)

    expandedIctItems.value = {
        ...expandedIctItems.value,
        [key]: !expandedIctItems.value[key],
    }
}

const validateIctAssetRows = (
    rows,
    errorBag,
    expectedCount,
    category = 'ict'
) => {
    const target =
        ictEquipmentCount(
            expectedCount
        )

    const editorRows =
        ictEditorAssetRows(rows)

    if (editorRows.length !== target) {
        errorBag.ict_assets =
            `Property Details must contain exactly ${target} row${target === 1 ? '' : 's'} to match the Count.`
    }

    const seen = new Set()

    for (
        let index = 0;
        index < target;
        index += 1
    ) {
        const asset =
            editorRows[index]
            || {
                description: '',
                accessories: '',
                property_number: '',
                current_user: '',
                status: '',
                mr_personnel_id: '',
                mr: '',
            }

        const description =
            String(
                asset.description
                ?? ''
            ).trim()

        const propertyNumber =
            String(
                asset.property_number
                ?? ''
            ).trim()

        if (
            (
                category === 'ict'
                || isPropertyTrackedOtherCategory(
                    category
                )
            )
            && !description
        ) {
            errorBag[
                `ict_assets.${index}.description`
            ] =
                'Description is required.'
        }

        if (!propertyNumber) {
            errorBag[
                `ict_assets.${index}.property_number`
            ] =
                'Property Number is required.'

            continue
        }

        const key =
            propertyNumber.toLowerCase()

        if (seen.has(key)) {
            errorBag[
                `ict_assets.${index}.property_number`
            ] =
                'Property Number must be unique for this item.'
        }

        const dateAcquired =
            normalizeIctDateInput(
                asset.date_acquired
            )

        const lifeSpanEnded =
            normalizeIctDateInput(
                asset.life_span_ended
            )

        if (
            category === 'ict'
            && asset.date_acquired
            && !dateAcquired
        ) {
            errorBag[
                `ict_assets.${index}.date_acquired`
            ] =
                'Enter a valid Date Acquired.'
        }

        if (
            category === 'ict'
            && asset.life_span_ended
            && !lifeSpanEnded
        ) {
            errorBag[
                `ict_assets.${index}.life_span_ended`
            ] =
                'Enter a valid Life Span Ended date.'
        }

        if (
            category === 'ict'
            && !isValidIctDateRange(
                dateAcquired,
                lifeSpanEnded
            )
        ) {
            errorBag[
                `ict_assets.${index}.life_span_ended`
            ] =
                'Life Span Ended cannot be earlier than Date Acquired.'
        }

        if (
            category === 'ict'
            && !normalizeIctAssetStatus(
                asset.status
            )
        ) {
            errorBag[
                `ict_assets.${index}.status`
            ] =
                'Select Working or Returned.'
        }

        seen.add(key)
    }
}

/*
 * Normal ICT:
 * Count = exact number of Property Detail rows.
 *
 * Subscription ICT:
 * No Property Detail rows.
 */
watch(
    [
        () => activeTab.value,
        () => newItemForm.value.other_category,
        () => newItemForm.value.unit,
        () =>
            newItemForm.value
                .currently_available,
    ],
    ([tab, otherCategory, unit, count]) => {
        const usesPropertyTracking =
            (
                tab === 'ict'
                && !isIctSubscription(unit)
            )
            || (
                tab === 'other'
                && isPropertyTrackedOtherCategory(
                    otherCategory
                )
            )

        if (!usesPropertyTracking) {
            newItemForm.value.ict_assets = []
            return
        }

        const syncedRows =
            syncIctAssetRowsToCount(
                newItemForm.value.ict_assets,
                count
            )

        newItemForm.value.ict_assets =
            tab === 'ict'
                ? syncedRows.map(
                    (asset) => ({
                        ...asset,
                        status:
                            normalizeIctAssetStatus(
                                asset.status
                            )
                            || 'working',
                    })
                )
                : syncedRows
    }
)

watch(
    [
        () => fullEditForm.value.category,
        () => fullEditForm.value.unit,
        () =>
            fullEditForm.value
                .currently_available,
    ],
    ([category, unit, count]) => {
        const usesPropertyTracking =
            (
                category === 'ict'
                && !isIctSubscription(unit)
            )
            || isPropertyTrackedOtherCategory(
                category
            )

        if (!usesPropertyTracking) {
            fullEditForm.value.ict_assets = []
            return
        }

        const syncedRows =
            syncIctAssetRowsToCount(
                fullEditForm.value.ict_assets,
                count
            )

        fullEditForm.value.ict_assets =
            category === 'ict'
                ? syncedRows.map(
                    (asset) => ({
                        ...asset,
                        status:
                            normalizeIctAssetStatus(
                                asset.status
                            )
                            || 'working',
                    })
                )
                : syncedRows
    }
)


const ictQuantityLabel = (itemOrUnit) => {
    if (isIctMonthBased(itemOrUnit)) {
        return 'Month(s)'
    }

    if (isIctYearBased(itemOrUnit)) {
        return 'Year(s)'
    }

    return 'Count'
}

const ictQuantityDisplay = (item) => {
    const value =
        currentAvailableValue(item)

    if (value === null) {
        return '—'
    }

    if (isIctMonthBased(item)) {
        return `${value} ${value === 1 ? 'Month' : 'Months'}`
    }

    if (isIctYearBased(item)) {
        return `${value} ${value === 1 ? 'Year' : 'Years'}`
    }

    return String(value)
}

const currentTitle = computed(() => {
    if (activeTab.value === 'supplies') {
        return 'Supplies Inventory'
    }

    if (activeTab.value === 'ict') {
        return 'ICT'
    }

    if (activeTab.value === 'returned') {
        return 'Returned ICT'
    }

if (
        activeTab.value
        === 'reconciliation'
    ) {
        return `${
            reconciliationReferenceCategoryLabel(
                reconciliationReferenceCategory.value
            )
        } Reconciliation`
    }

    return `Other Items · ${currentOtherCategoryLabel.value}`
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
    { value: 'MONTH', label: 'Month (Subscription)' },
    { value: 'YEAR', label: 'Year (Subscription)' },
    { value: 'UNIT', label: 'Unit' },
    { value: 'LOT', label: 'Lot' },
    { value: 'PAX', label: 'Pax' },
]

const unitOptionLabel = (
    value,
    category
) => {
    const normalized =
        normalizeCustomUnitValue(value)

    const baseOptions =
        category === 'ict'
            ? ictUnitOptions
            : suppliesUnitOptions

    const existing =
        baseOptions.find(
            (option) =>
                option.value === normalized
        )

    return existing?.label
        ?? normalized
}

const inventoryUnitsForCategory = (
    category
) => {
    return [
        ...new Set(
            (props.inventoryItems || [])
                .filter(
                    (item) =>
                        String(
                            item?.category || ''
                        )
                            .trim()
                            .toLowerCase()
                        === category
                )
                .map(
                    (item) =>
                        normalizeCustomUnitValue(
                            item?.unit
                        )
                )
                .filter(Boolean)
        ),
    ]
}

const mergedUnitOptions = (
    category,
    baseOptions
) => {
    const seen = new Set(
        baseOptions.map(
            (option) =>
                option.value
        )
    )

    const extras = [
        ...(
            customUnitValues.value[
                category
            ]
            || []
        ),
        ...inventoryUnitsForCategory(
            category
        ),
    ]
        .map(
            normalizeCustomUnitValue
        )
        .filter(Boolean)
        .filter((value) => {
            if (seen.has(value)) {
                return false
            }

            seen.add(value)
            return true
        })
        .sort((a, b) =>
            a.localeCompare(
                b,
                undefined,
                {
                    sensitivity: 'base',
                }
            )
        )

    return [
        ...baseOptions,
        ...extras.map((value) => ({
            value,
            label:
                unitOptionLabel(
                    value,
                    category
                ),
        })),
    ]
}

const suppliesUnitOptionsWithCustom =
    computed(() =>
        mergedUnitOptions(
            'supplies',
            suppliesUnitOptions
        )
    )

const ictUnitOptionsWithCustom =
    computed(() =>
        mergedUnitOptions(
            'ict',
            ictUnitOptions
        )
    )

const unitOptions = computed(() => {
    if (activeTab.value === 'supplies') {
        return (
            suppliesUnitOptionsWithCustom
                .value
        )
    }

    if (activeTab.value === 'ict') {
        return (
            ictUnitOptionsWithCustom
                .value
        )
    }

    return []
})

const addCustomUnitToCategory = (
    category,
    rawValue
) => {
    const value =
        normalizeCustomUnitValue(
            rawValue
        )

    if (
        !['supplies', 'ict'].includes(
            category
        )
    ) {
        return {
            ok: false,
            error:
                'Custom units are only available for Supplies and ICT.',
        }
    }

    if (!value) {
        return {
            ok: false,
            error:
                'Enter a unit name.',
        }
    }

    if (value.length > 50) {
        return {
            ok: false,
            error:
                'Unit must not exceed 50 characters.',
        }
    }

    const exists =
        mergedUnitOptions(
            category,
            category === 'ict'
                ? ictUnitOptions
                : suppliesUnitOptions
        )
            .some(
                (option) =>
                    option.value === value
            )

    if (!exists) {
        customUnitValues.value = {
            ...customUnitValues.value,
            [category]: [
                ...(
                    customUnitValues.value[
                        category
                    ]
                    || []
                ),
                value,
            ],
        }
    }

    return {
        ok: true,
        value,
    }
}

const saveToolbarCustomUnit = () => {
    const result =
        addCustomUnitToCategory(
            activeTab.value,
            addUnitValue.value
        )

    if (!result.ok) {
        addUnitError.value =
            result.error

        return
    }

    unitFilter.value = 'all'

    addUnitValue.value = ''
    addUnitError.value = ''
    addUnitEditorOpen.value = false
}

const saveAddCustomUnit = () => {
    const result =
        addCustomUnitToCategory(
            activeTab.value,
            addUnitValue.value
        )

    if (!result.ok) {
        addUnitError.value =
            result.error

        return
    }

    newItemForm.value.unit =
        result.value

    addUnitValue.value = ''
    addUnitError.value = ''
    addUnitEditorOpen.value = false
}

const editUnitOptions = computed(() => {
    if (
        fullEditForm.value.category
        === 'supplies'
    ) {
        return (
            suppliesUnitOptionsWithCustom
                .value
        )
    }

    if (
        fullEditForm.value.category
        === 'ict'
    ) {
        return (
            ictUnitOptionsWithCustom
                .value
        )
    }

    return []
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
    const category =
        String(item?.category || '')
            .trim()
            .toLowerCase()

    if (category !== 'supplies') {
        return []
    }

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
        description: '',
        other_category:
            otherCategoryFilter.value,
        location: '',
        unit: '',
        inventory_year:
            Number(yearFilter.value) || 2026,
        fixed: '',
        currently_available: '',
        quarters:
            quarterFilter.value === 'all'
                ? []
                : [quarterFilter.value],
        quarter_stock:
            quarterFilter.value === 'all'
                ? {}
                : {
                    [quarterFilter.value]: {
                        current: '',
                    },
                },
        ict_assets: [],
        remarks: '',
    }

    addItemErrors.value = {}
}

const openAddItemModal = () => {
    if (!canManageInventory.value) {
        return
    }

    resetNewItemForm()
    showAddItemModal.value = true
}

const closeAddItemModal = () => {
    showAddItemModal.value = false
    resetNewItemForm()
}

const toggleAllNewItemQuarters = (event) => {
    const selected =
        event.target.checked
            ? [...quarterValues]
            : []

    const nextStock = {}

    selected.forEach((quarter) => {
        nextStock[quarter] = {
            current:
                newItemForm.value
                    .quarter_stock?.[quarter]
                    ?.current
                ?? '',
        }
    })

    newItemForm.value.quarters =
        selected

    newItemForm.value.quarter_stock =
        nextStock
}

const toggleNewItemQuarter = (
    quarter,
    checked
) => {
    const selected =
        checked
            ? [
                ...new Set([
                    ...newItemForm.value.quarters,
                    quarter,
                ]),
            ]
            : newItemForm.value.quarters
                .filter(
                    (value) =>
                        value !== quarter
                )

    const nextStock = {}

    selected.forEach((value) => {
        nextStock[value] = {
            current:
                newItemForm.value
                    .quarter_stock?.[value]
                    ?.current
                ?? '',
        }
    })

    newItemForm.value.quarters =
        selected

    newItemForm.value.quarter_stock =
        nextStock
}

const addNewItem = () => {
    if (!canManageInventory.value) {
        return
    }

    addItemErrors.value = {}

    const itemName = String(
        newItemForm.value.item || ''
    ).trim()

    const remarks = String(
        newItemForm.value.remarks ?? ''
    ).trim()

    const isSupplies =
        activeTab.value === 'supplies'

    const isIct =
        activeTab.value === 'ict'

    const isOtherItems =
        activeTab.value === 'other'

    const category =
        isOtherItems
            ? otherCategoryForSave(
                newItemForm.value.other_category
            )
            : activeTab.value

    const location = String(
        newItemForm.value.location || ''
    ).trim()

    const requiresItemCount =
        otherCategoryHasCount(category)

    const unit = String(
        newItemForm.value.unit || ''
    )
        .trim()
        .toUpperCase()

    const inventoryYear =
        Number(
            newItemForm.value.inventory_year
        )

    const quarters = [
        ...new Set(
            newItemForm.value.quarters
        ),
    ].filter((quarter) =>
        quarterValues.includes(quarter)
    )

    const hasFixedValue =
        isSupplies
        && newItemForm.value.fixed !== ''
        && newItemForm.value.fixed !== null
        && newItemForm.value.fixed !== undefined

    const fixedValue = hasFixedValue
        ? Number(newItemForm.value.fixed)
        : null

    const currentlyAvailable =
        isSupplies || isIct || isOtherItems
            ? Number(
                newItemForm.value.currently_available
            )
            : null

    if (!itemName) {
        addItemErrors.value.item =
            'Item name is required.'
    }

    /*
     * OTHER ITEMS
     * Table fields = Item, Location, Remarks.
     * Therefore Add Item uses those same user-facing fields only.
     */
    if (isOtherItems) {
        if (!location) {
            addItemErrors.value.location =
                'Location is required.'
        }

        if (
            requiresItemCount
            && (
                newItemForm.value.currently_available === ''
                || newItemForm.value.currently_available === null
                || newItemForm.value.currently_available === undefined
                || !Number.isInteger(
                    Number(
                        newItemForm.value.currently_available
                    )
                )
                || Number(
                    newItemForm.value.currently_available
                ) < 0
            )
        ) {
            addItemErrors.value.currently_available =
                'Enter a valid count.'
        }
    } else {
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

        if (
            isSupplies
            && !quarters.length
        ) {
            addItemErrors.value.quarters =
                'Select at least one applicable quarter.'
        }
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
    }

    if (isSupplies) {
        quarters.forEach((quarter) => {
            const raw =
                newItemForm.value
                    .quarter_stock?.[quarter]
                    ?.current

            const value =
                Number(raw)

            if (
                raw === ''
                || raw === null
                || raw === undefined
                || !Number.isInteger(value)
                || value < 0
            ) {
                addItemErrors.value[
                    `quarter_stock.${quarter}.current`
                ] =
                    `Enter the starting/current quantity for ${quarter.toUpperCase()}.`
            }
        })
    }


    if (isIct) {
        if (
            newItemForm.value.currently_available === ''
            || newItemForm.value.currently_available === null
            || newItemForm.value.currently_available === undefined
            || !Number.isInteger(currentlyAvailable)
            || currentlyAvailable < 0
        ) {
            addItemErrors.value.currently_available =
                isIctMonthBased(unit)
                    ? 'Enter a valid number of month(s).'
                    : isIctYearBased(unit)
                        ? 'Enter a valid number of year(s).'
                        : 'Enter a valid count.'
        }
    }


    if (
        (
            isIct
            && !isIctSubscription(unit)
        )
        || (
            isOtherItems
            && isPropertyTrackedOtherCategory(
                category
            )
        )
    ) {
        validateIctAssetRows(
            newItemForm.value.ict_assets,
            addItemErrors.value,
            currentlyAvailable,
            category
        )
    }

    const quarterStockPayload = {}

    if (isSupplies) {
        quarters.forEach((quarter) => {
            const current =
                Number(
                    newItemForm.value
                        .quarter_stock?.[quarter]
                        ?.current
                )

            quarterStockPayload[quarter] = {
                current,
                remarks:
                    String(
                        newItemForm.value
                            .quarter_stock?.[quarter]
                            ?.remarks
                        ?? ''
                    ).trim(),
            }
        })
    }

    const aggregateQuarterCurrent =
        Object.values(
            quarterStockPayload
        ).reduce(
            (total, entry) =>
                total
                + (
                    Number.isFinite(
                        Number(entry.current)
                    )
                        ? Number(entry.current)
                        : 0
                ),
            0
        )

    const effectiveItemName =
        itemName

    const duplicateExists =
        normalizedInventoryItems.value.some((item) => {
            if (
                item.category !== category
                || Number(item.id || 0) < 0
            ) {
                return false
            }

            if (
                String(item.item || '')
                    .trim()
                    .toLowerCase()
                !== effectiveItemName.toLowerCase()
            ) {
                return false
            }

            if (isIct) {
                return (
                    Number(item.inventory_year)
                        === inventoryYear
                    &&
                    String(item.unit || '')
                        .trim()
                        .toUpperCase()
                        === unit
                )
            }

            if (isOtherItems) {
                return String(item.location || '')
                    .trim()
                    .toLowerCase()
                    === location.toLowerCase()
            }

            return (
                Number(item.inventory_year)
                    === inventoryYear
                &&
                String(item.unit || '')
                    .trim()
                    .toUpperCase()
                    === unit
            )
        })

    if (duplicateExists) {
        if (isIct) {
            addItemErrors.value.item =
                `This ICT item and unit already exist for ${inventoryYear}.`
        } else {
            addItemErrors.value.item =
                isOtherItems
                    ? 'This item already exists in the same location.'
                    : `This item and unit already exist for ${inventoryYear}.`
        }
    }

    if (
        Object.keys(
            addItemErrors.value
        ).length
    ) {
        return
    }

    const payload = {
        category,
        item: effectiveItemName,
        description: null,
        remarks,
    }

    if (isOtherItems) {
        payload.location = location
        payload.currently_available =
            requiresItemCount
                ? Number(
                    newItemForm.value.currently_available
                )
                : null
        payload.ict_assets =
            isPropertyTrackedOtherCategory(
                category
            )
                ? normalizeIctAssets(
                    newItemForm.value.ict_assets
                )
                : []
    } else if (isIct) {
        payload.location = null
        payload.unit = unit
        payload.inventory_year = inventoryYear
        payload.fixed_value = null
        payload.currently_available =
            currentlyAvailable
        payload.ict_assets =
            isIctSubscription(unit)
                ? []
                : normalizeIctAssets(
                    newItemForm.value.ict_assets
                )
    } else {
        payload.location = null
        payload.unit = unit
        payload.inventory_year = inventoryYear
        payload.quarters = quarters
        payload.fixed_value = fixedValue
        payload.quarter_stock =
            quarterStockPayload
        payload.currently_available =
            aggregateQuarterCurrent
    }

    router.post(
        '/dts/inventory',
        payload,
        {
            preserveScroll: true,

            onSuccess: () => {
                if (isOtherItems) {
                    otherCategoryFilter.value =
                        otherFilterValueForCategory(
                            category
                        )
                } else {
                    yearFilter.value =
                        inventoryYear
                }

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

    /*
     * Other Items are location-based records.
     * No Year, Unit, or Quarter filtering applies to them.
     */
    if (activeTab.value === 'other') {
        return currentItems.value.filter((item) => {
            return (
                !term
                ||
                String(item.item || '')
                    .toLowerCase()
                    .includes(term)
                ||
                String(item.location || '')
                    .toLowerCase()
                    .includes(term)
                ||
                String(item.remarks || '')
                    .toLowerCase()
                    .includes(term)
                ||
                (
                    item.category === 'supplies'
                    && quarterRemarksEntries(
                        item,
                        'all'
                    ).some(
                        (entry) =>
                            entry.remarks
                                .toLowerCase()
                                .includes(term)
                    )
                )
            )
        })
    }

    const filtered =
        currentItems.value.filter((item) => {
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
                String(item.description || '')
                    .toLowerCase()
                    .includes(term)
                ||
                String(item.remarks || '')
                    .toLowerCase()
                    .includes(term)
                ||
                (
                    item.category === 'supplies'
                    && quarterRemarksEntries(
                        item,
                        'all'
                    ).some(
                        (entry) =>
                            entry.remarks
                                .toLowerCase()
                                .includes(term)
                    )
                )
                ||
                (
                    ['ict', 'returned'].includes(
                        activeTab.value
                    )
                    && filteredIctAssetDetails(item)
                        .some((asset) =>
                            [
                                asset.description,
                                asset.accessories,
                                asset.property_number,
                                asset.current_user,
                                asset.mr,
                                asset.date_acquired,
                                asset.life_span_ended,
                                ictAssetStatusLabel(
                                    asset.status
                                ),
                            ]
                                .some((value) =>
                                    String(value || '')
                                        .toLowerCase()
                                        .includes(term)
                                )
                        )
                )

            const matchesUnit =
                unitFilter.value === 'all'
                ||
                item.unit === unitFilter.value

            const matchesQuarter =
                activeTab.value !== 'supplies'
                || quarterFilter.value === 'all'
                || inferredItemQuarters(item)
                    .includes(
                        quarterFilter.value
                    )

            const isIctLikeTab =
                ['ict', 'returned'].includes(
                    activeTab.value
                )

            const matchesMr =
                !isIctLikeTab
                || mrFilter.value === 'all'
                || filteredIctAssetDetails(
                    item
                ).length > 0

            const matchesIctWorkingRows =
                activeTab.value !== 'ict'
                || isIctSubscription(item)
                || filteredIctAssetDetails(
                    item
                ).length > 0

            return (
                matchesYear
                && matchesSearch
                && matchesUnit
                && matchesQuarter
                && matchesMr
                && matchesIctWorkingRows
            )
        })

    /*
     * ICT ordering:
     * Subscription items first (MONTH / YEAR),
     * followed by normal ICT items.
     */
    if (activeTab.value === 'ict') {
        return [...filtered].sort((a, b) => {
            const aSubscription =
                isIctSubscription(a)
                    ? 0
                    : 1

            const bSubscription =
                isIctSubscription(b)
                    ? 0
                    : 1

            if (
                aSubscription
                !== bSubscription
            ) {
                return (
                    aSubscription
                    - bSubscription
                )
            }

            /*
             * MONTH before YEAR inside subscriptions.
             */
            const unitOrder = {
                MONTH: 0,
                YEAR: 1,
                UNIT: 2,
                LOT: 3,
                PAX: 4,
            }

            const aUnit =
                String(a.unit || '')
                    .trim()
                    .toUpperCase()

            const bUnit =
                String(b.unit || '')
                    .trim()
                    .toUpperCase()

            const aOrder =
                unitOrder[aUnit]
                ?? 99

            const bOrder =
                unitOrder[bUnit]
                ?? 99

            if (aOrder !== bOrder) {
                return aOrder - bOrder
            }

            return String(a.item || '')
                .localeCompare(
                    String(b.item || ''),
                    undefined,
                    {
                        sensitivity: 'base',
                    }
                )
        })
    }

    return filtered
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
        suppliesUnitOptionsWithCustom
            .value
            .map(
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

const activeFilteredCount = computed(() =>
    activeTab.value === 'returned'
        ? filteredReturnedAssetRows.value.length
        : filteredItems.value.length
)

const totalPages = computed(() => {
    return Math.max(
        1,
        Math.ceil(
            activeFilteredCount.value
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

const paginatedReturnedAssets = computed(() => {
    const start =
        (currentPage.value - 1)
        * perPage

    return filteredReturnedAssetRows.value.slice(
        start,
        start + perPage
    )
})

const showingFrom = computed(() => {
    if (!activeFilteredCount.value) {
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
        activeFilteredCount.value
    )
})

/*
|--------------------------------------------------------------------------
| COUNTERS
|--------------------------------------------------------------------------
*/

const withRemarksCount = computed(() => {
    return currentItems.value.filter(
        (item) => {
            const hasGeneral =
                String(
                    item.remarks || ''
                ).trim() !== ''

            const hasQuarter =
                item.category === 'supplies'
                && quarterRemarksEntries(
                    item,
                    'all'
                ).length > 0

            return hasGeneral || hasQuarter
        }
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
    mrFilter.value = 'all'
    currentPage.value = 1
}

const currentReconciliationCategory = computed(() => {
    if (activeTab.value === 'supplies') {
        return 'supplies'
    }

    if (
        activeTab.value === 'ict'
        || activeTab.value === 'returned'
    ) {
        return 'ict'
    }

    if (activeTab.value === 'other') {
        return otherCategoryFilter.value
    }

    return reconciliationReferenceCategory.value
})

const currentReconciliationLabel = computed(() =>
    reconciliationReferenceCategoryLabel(
        currentReconciliationCategory.value
    )
)

const openCurrentTabReconciliation = () => {
    if (activeTab.value === 'reconciliation') {
        return
    }

    reconciliationReturnTab.value =
        activeTab.value

    reconciliationReturnOtherCategory.value =
        otherCategoryFilter.value

    reconciliationReferenceCategory.value =
        currentReconciliationCategory.value

    reconciliationSearch.value = ''
    reconciliationStatusFilter.value = 'all'

    activeTab.value =
        'reconciliation'
}

const backFromReconciliation = () => {
    const destination =
        reconciliationReturnTab.value

    if (destination === 'other') {
        otherCategoryFilter.value =
            reconciliationReturnOtherCategory.value
    }

    switchTab(
        ['supplies', 'ict', 'other'].includes(
            destination
        )
            ? destination
            : 'supplies'
    )
}

const switchOtherCategory = (category) => {
    if (
        !otherCategoryFilterValues.includes(
            category
        )
    ) {
        return
    }

    otherCategoryFilter.value = category
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
        mrFilter,
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

const openHistoryModal = async (
    item,
    asset = null
) => {
    if (!item?.id) {
        return
    }

    historyItem.value = item

    historyPropertyNumber.value =
        String(
            asset?.property_number
            ?? ''
        ).trim()

    historyPropertyDescription.value =
        String(
            asset?.description
            ?? ''
        ).trim()

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
    historyPropertyNumber.value = ''
    historyPropertyDescription.value = ''
}


/*
|--------------------------------------------------------------------------
| HISTORY DISPLAY HELPERS
|--------------------------------------------------------------------------
*/

const historyIsPropertyScoped = computed(
    () =>
        String(
            historyPropertyNumber.value
            || ''
        ).trim() !== ''
)

const normalizedHistoryPropertyNumber = (value) =>
    String(value ?? '')
        .trim()
        .toLowerCase()

const historyChangePropertyNumbers = (change) => {
    const values = []

    if (
        Array.isArray(
            change?.asset_property_numbers
        )
    ) {
        values.push(
            ...change.asset_property_numbers
        )
    }

    if (
        change?.asset_property_number
        !== null
        && change?.asset_property_number
        !== undefined
    ) {
        values.push(
            change.asset_property_number
        )
    }

    /*
     * Backward-compatible fallback for older release
     * history rows that may not yet have explicit metadata.
     */
    if (
        String(change?.field || '')
            === 'release_property_number'
    ) {
        values.push(
            change?.new,
            change?.old
        )
    }

    return [
        ...new Set(
            values
                .map(
                    (value) =>
                        String(value ?? '')
                            .trim()
                )
                .filter(Boolean)
        ),
    ]
}

const historyChangeMatchesProperty = (
    change,
    propertyNumber
) => {
    const target =
        normalizedHistoryPropertyNumber(
            propertyNumber
        )

    if (!target) {
        return false
    }

    return historyChangePropertyNumbers(
        change
    ).some(
        (value) =>
            normalizedHistoryPropertyNumber(
                value
            ) === target
    )
}

const propertyScopedHistoryMeta = (
    history,
    changes
) => {
    const fields =
        changes.map(
            (change) =>
                String(
                    change?.field || ''
                )
        )

    const propertyNumber =
        String(
            historyPropertyNumber.value
            || ''
        ).trim()

    const hasRelease =
        history?.action === 'release'
        || fields.some(
            (field) =>
                field.startsWith('release_')
        )

    const hasAdded =
        fields.includes(
            'ict_asset_added'
        )

    const hasRemoved =
        fields.includes(
            'ict_asset_removed'
        )

    if (hasRelease) {
        return {
            event_title:
                'Released Property',
            event_summary:
                `Property ${propertyNumber} was released.`,
        }
    }

    if (hasAdded) {
        return {
            event_title:
                'Added ICT Unit',
            event_summary:
                `Property ${propertyNumber} was added.`,
        }
    }

    if (hasRemoved) {
        return {
            event_title:
                'Removed ICT Unit',
            event_summary:
                `Property ${propertyNumber} was removed.`,
        }
    }

    return {
        event_title:
            'Edited Property Details',
        event_summary:
            `Property ${propertyNumber} was updated.`,
    }
}

const visibleInventoryHistories = computed(() => {
    if (!historyIsPropertyScoped.value) {
        /*
         * Item-level History intentionally shows every
         * activity for every property under this item.
         */
        return inventoryHistories.value
    }

    const target =
        String(
            historyPropertyNumber.value
            || ''
        ).trim()

    return inventoryHistories.value
        .map((history) => {
            const changes =
                Array.isArray(
                    history?.changes
                )
                    ? history.changes
                        .filter(
                            (change) =>
                                historyChangeMatchesProperty(
                                    change,
                                    target
                                )
                        )
                    : []

            /*
             * IMPORTANT:
             * Do not show an item-level or another property's
             * history merely because it happened in the same
             * inventory update transaction.
             */
            if (!changes.length) {
                return null
            }

            const scopedMeta =
                propertyScopedHistoryMeta(
                    history,
                    changes
                )

            return {
                ...history,

                /*
                 * The property modal is intentionally scoped
                 * to one Property Number only.
                 */
                property_numbers: [
                    target,
                ],

                changes,

                event_title:
                    scopedMeta.event_title,

                event_summary:
                    scopedMeta.event_summary,
            }
        })
        .filter(Boolean)
})


const historyActionToneClass = (history) => {
    const title =
        String(
            history?.event_title
            || historyActionLabel(history)
            || ''
        ).toLowerCase()

    if (title.includes('release')) {
        return 'border-rose-200 bg-rose-50 text-rose-700'
    }

    if (
        title.includes('added')
        || title.includes('add ')
    ) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    }

    if (title.includes('property')) {
        return 'border-blue-200 bg-blue-50 text-blue-700'
    }

    return 'border-slate-200 bg-slate-50 text-slate-700'
}

const historyEventSummary = (history) => {
    return String(
        history?.event_summary
        || ''
    ).trim()
}

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
    const eventTitle =
        String(
            history?.event_title
            || ''
        ).trim()

    if (eventTitle) {
        return eventTitle
    }

    if (history?.action === 'release') {
        return 'Released Stock'
    }

    if (history?.action === 'edit') {
        return 'Edited Item Details'
    }

    return 'Updated Inventory'
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

    if (field === 'quarter_remarks') {
        return String(value || '').trim() || '—'
    }

    if (field === 'quarter_stock') {
        const stock =
            normalizeQuarterStock(value)

        const parts =
            quarterValues
                .filter(
                    (quarter) =>
                        stock?.[quarter]
                )
                .map((quarter) => {
                    const entry =
                        stock[quarter]

                    return `${quarter.toUpperCase()}: ${entry.current} remaining / ${entry.released} released`
                })

        return parts.length
            ? parts.join(' · ')
            : '—'
    }

    if (field === 'ict_assets') {
        const assets =
            normalizeIctAssets(value)

        if (!assets.length) {
            return '—'
        }

        return assets
            .map((asset) =>
                `${asset.property_number || '—'} — ${asset.current_user || 'Unassigned'}`
            )
            .join(' · ')
    }

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

        if (category === 'ict') {
            return 'ICT'
        }

        if (category === 'supplies') {
            return 'Supplies'
        }

        if (
            furnitureFixtureCategories.includes(
                category
            )
        ) {
            return 'Furniture/Fixtures'
        }

        return otherCategoryOptions.find(
            (option) =>
                option.value === category
        )?.label || String(value)
    }

    if (field === 'ict_asset_status') {
        return (
            normalizeIctAssetStatus(value)
            === 'returned'
                ? 'Returned'
                : 'Working'
        )
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

const currentAvailableValue = (
    item,
    selectedQuarter = quarterFilter.value
) => {
    const category =
        String(item?.category || '')
            .trim()
            .toLowerCase()

    if (
        category === 'supplies'
        && hasQuarterStock(item)
    ) {
        return quarterStockValue(
            item,
            'current',
            selectedQuarter
        )
    }

    if (
        category === 'supplies'
        && !hasQuarterStock(item)
        && inferredItemQuarters(item).length > 1
        && selectedQuarter !== 'all'
    ) {
        /*
         * Legacy multi-quarter rows only have one old global balance.
         * Do not pretend that same number belongs to every quarter.
         */
        return null
    }

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

const quantityReleasedValue = (
    item,
    selectedQuarter = quarterFilter.value
) => {
    const category =
        String(item?.category || '')
            .trim()
            .toLowerCase()

    if (
        category === 'supplies'
        && hasQuarterStock(item)
    ) {
        return quarterStockValue(
            item,
            'released',
            selectedQuarter
        )
    }

    if (
        category === 'supplies'
        && !hasQuarterStock(item)
        && inferredItemQuarters(item).length > 1
        && selectedQuarter !== 'all'
    ) {
        return null
    }

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
            currentAvailableValue(
                item,
                selectedQuarter
            )

        if (currentlyAvailable === null) {
            return null
        }

        return Math.max(
            0,
            fixed - currentlyAvailable
        )
    }

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
    const current =
        [...fullEditForm.value.quarters]

    const adding =
        !current.includes(quarter)

    const nextQuarters =
        adding
            ? [...current, quarter]
            : current.filter(
                (value) =>
                    value !== quarter
            )

    const nextStock = {}

    nextQuarters.forEach((value) => {
        const originalEntry =
            originalEditQuarterStock.value?.[
                value
            ]

        const needsActivation =
            !originalEntry
            || !quarterEntryHasActivity(
                originalEntry
            )

        nextStock[value] = {
            ...(
                fullEditForm.value
                    .quarter_stock?.[value]
                || {}
            ),
            current:
                fullEditForm.value
                    .quarter_stock?.[value]
                    ?.current
                ?? '',
            ...(needsActivation
                ? { activate: true }
                : {}
            ),
        }
    })

    fullEditForm.value.quarters =
        nextQuarters

    fullEditForm.value.quarter_stock =
        nextStock
}

const toggleAllFullEditQuarters = () => {
    const nextQuarters =
        allFullEditQuartersSelected.value
            ? []
            : [...quarterValues]

    const nextStock = {}

    nextQuarters.forEach((quarter) => {
        nextStock[quarter] = {
            ...(
                fullEditForm.value
                    .quarter_stock?.[quarter]
                || {}
            ),
            current:
                fullEditForm.value
                    .quarter_stock?.[quarter]
                    ?.current
                ?? '',
        }
    })

    fullEditForm.value.quarters =
        nextQuarters

    fullEditForm.value.quarter_stock =
        nextStock
}

const openDeleteItemModal = (item) => {
    if (!canManageInventory.value || !item?.id) {
        return
    }

    deletingItem.value =
        normalizeInventoryItem(item)

    showDeleteItemModal.value = true
}

const closeDeleteItemModal = () => {
    if (deleteItemProcessing.value) {
        return
    }

    showDeleteItemModal.value = false
    deletingItem.value = null
}

const confirmDeleteItem = () => {
    if (
        !canManageInventory.value
        || !deletingItem.value?.id
        || deleteItemProcessing.value
    ) {
        return
    }

    deleteItemProcessing.value = true

    router.delete(
        `/dts/inventory/${deletingItem.value.id}`,
        {
            preserveScroll: true,

            onSuccess: () => {
                showDeleteItemModal.value = false
                deletingItem.value = null
                currentPage.value = 1

                router.reload({
                    only: ['inventoryItems'],
                    preserveScroll: true,
                    preserveState: true,
                })
            },

            onFinish: () => {
                deleteItemProcessing.value = false
            },
        }
    )
}

const openFullEditModal = (item) => {
    if (!canManageInventory.value) {
        return
    }

    resetNewIctAssetForm()
    resetAddOtherCount()

    if (!item?.id) {
        return
    }

    const normalized = normalizeInventoryItem(item)

    const normalizedQuarters =
        inferredItemQuarters(normalized)

    const storedQuarterStock =
        normalizeQuarterStock(
            normalized.quarter_stock
        )

    const editQuarterStock = {}

    normalizedQuarters.forEach((quarter) => {
        if (storedQuarterStock?.[quarter]) {
            editQuarterStock[quarter] = {
                ...storedQuarterStock[quarter],
                current:
                    storedQuarterStock[quarter]
                        .current,
            }

            return
        }

        editQuarterStock[quarter] = {
            current:
                normalizedQuarters.length === 1
                && normalized.currently_available !== null
                && normalized.currently_available !== undefined
                    ? normalized.currently_available
                    : '',
        }
    })

    fullEditingItem.value = normalized
    fullEditForm.value = {
        category: normalized.category || 'supplies',
        item: String(normalized.item || ''),
        description:
            String(normalized.description || ''),
        location: String(normalized.location || ''),
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
            quarters: [...normalizedQuarters],
        quarter_stock:
            editQuarterStock,
        ict_assets:
            (
                (
                    normalized.category === 'ict'
                    && !isIctSubscription(
                        normalized.unit
                    )
                )
                || isPropertyTrackedOtherCategory(
                    normalized.category
                )
            )
                ? syncIctAssetRowsToCount(
                    normalized.ict_assets,
                    normalized.currently_available
                ).map((asset) => ({
                    ...asset,
                    description:
                        normalized.category === 'ict'
                            ? String(
                                asset.description
                                || normalized.description
                                || ''
                            ).trim()
                            : asset.description,
                }))
                : [],

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
    resetNewIctAssetForm()
    resetAddOtherCount()
}

const openIctAssetEditModal = (
    item,
    asset
) => {
    if (
        !canManageInventory.value
        || !item?.id
        || !asset
    ) {
        return
    }

    const rowIndex =
        Number(asset?._index)

    if (
        !Number.isInteger(rowIndex)
        || rowIndex < 0
    ) {
        return
    }

    const normalizedAsset =
        ictEditorAssetRows([
            asset,
        ])[0]
        || {
            description: '',
            accessories: '',
            property_number: '',
            current_user: '',
            date_acquired: '',
            life_span_ended: '',
            status: 'working',
            mr_personnel_id: '',
            mr: '',
        }

    ictAssetEditingItem.value =
        normalizeInventoryItem(item)

    ictAssetEditingIndex.value =
        rowIndex

    ictAssetEditingOriginalPropertyNumber.value =
        String(
            normalizedAsset.property_number
            || ''
        ).trim()

    ictAssetEditForm.value = {
        description:
            normalizedAsset.description,
        accessories:
            normalizedAsset.accessories,
        property_number:
            normalizedAsset.property_number,
        current_user:
            normalizedAsset.current_user,
        date_acquired:
            normalizedAsset.date_acquired,
        life_span_ended:
            normalizedAsset.life_span_ended,
        status:
            normalizeIctAssetStatus(
                normalizedAsset.status
            )
            || 'working',
        mr_personnel_id:
            normalizedAsset.mr_personnel_id,
        mr:
            normalizedAsset.mr,
    }

    if (
        ictAssetEditForm.value
            .mr_personnel_id
    ) {
        syncAssetMr(
            ictAssetEditForm.value
        )
    }

    ictAssetEditErrors.value = {}
    showIctAssetEditModal.value = true
}

const individualPropertyEditIsOther = computed(
    () =>
        isPropertyTrackedOtherCategory(
            ictAssetEditingItem.value?.category
        )
)

const individualPropertyEditIsIct = computed(
    () =>
        String(
            ictAssetEditingItem.value?.category
            || ''
        )
            .trim()
            .toLowerCase()
        === 'ict'
)

const closeIctAssetEditModal = () => {
    if (ictAssetEditProcessing.value) {
        return
    }

    showIctAssetEditModal.value = false
    ictAssetEditingItem.value = null
    ictAssetEditingIndex.value = null
    ictAssetEditingOriginalPropertyNumber.value = ''
    ictAssetEditErrors.value = {}
}

const saveIctAssetEdit = () => {
    if (
        !canManageInventory.value
        || !ictAssetEditingItem.value?.id
        || !Number.isInteger(
            ictAssetEditingIndex.value
        )
    ) {
        return
    }

    ictAssetEditErrors.value = {}

    const category =
        String(
            ictAssetEditingItem.value
                ?.category || ''
        )
            .trim()
            .toLowerCase()

    const isOtherProperty =
        isPropertyTrackedOtherCategory(
            category
        )

    const isIctProperty =
        category === 'ict'

    if (
        !isIctProperty
        && !isOtherProperty
    ) {
        return
    }

    const description =
        String(
            ictAssetEditForm.value
                .description || ''
        ).trim()

    const accessories =
        String(
            ictAssetEditForm.value
                .accessories || ''
        ).trim()

    const propertyNumber =
        String(
            ictAssetEditForm.value
                .property_number || ''
        ).trim()

    const currentUser =
        String(
            ictAssetEditForm.value
                .current_user || ''
        ).trim()

    const dateAcquired =
        normalizeIctDateInput(
            ictAssetEditForm.value
                .date_acquired
        )

    const lifeSpanEnded =
        normalizeIctDateInput(
            ictAssetEditForm.value
                .life_span_ended
        )

    const status =
        normalizeIctAssetStatus(
            ictAssetEditForm.value.status
        )

    const mrPersonnelId =
        String(
            ictAssetEditForm.value
                .mr_personnel_id || ''
        ).trim()

    if (!description) {
        ictAssetEditErrors.value.description =
            'Description is required.'
    }

    if (!propertyNumber) {
        ictAssetEditErrors.value.property_number =
            'Property Number is required.'
    }

    /*
     * ICT-only fields.
     * Furniture/Fixtures only edit:
     * Description, Property Number, Current User.
     */
    if (isIctProperty) {
        if (!status) {
            ictAssetEditErrors.value.status =
                'Select Working or Returned.'
        }

        if (
            ictAssetEditForm.value.date_acquired
            && !dateAcquired
        ) {
            ictAssetEditErrors.value.date_acquired =
                'Enter a valid Date Acquired.'
        }

        if (
            ictAssetEditForm.value.life_span_ended
            && !lifeSpanEnded
        ) {
            ictAssetEditErrors.value.life_span_ended =
                'Enter a valid Life Span Ended date.'
        }

        if (
            !isValidIctDateRange(
                dateAcquired,
                lifeSpanEnded
            )
        ) {
            ictAssetEditErrors.value.life_span_ended =
                'Life Span Ended cannot be earlier than Date Acquired.'
        }
    }

    if (propertyNumber) {
        const duplicate =
            ictAssetDetails(
                ictAssetEditingItem.value
            ).some((asset) => {
                if (
                    Number(asset._index)
                    === Number(
                        ictAssetEditingIndex.value
                    )
                ) {
                    return false
                }

                return (
                    String(
                        asset.property_number
                        || ''
                    )
                        .trim()
                        .toLowerCase()
                    ===
                    propertyNumber.toLowerCase()
                )
            })

        if (duplicate) {
            ictAssetEditErrors.value.property_number =
                'Property Number must be unique for this item.'
        }
    }

    if (
        Object.keys(
            ictAssetEditErrors.value
        ).length
    ) {
        return
    }

    const assetPayload =
        isOtherProperty
            ? {
                description,
                property_number:
                    propertyNumber,
                current_user:
                    currentUser || null,
            }
            : {
                description,
                accessories:
                    accessories || null,
                property_number:
                    propertyNumber,
                current_user:
                    currentUser || null,
                date_acquired:
                    dateAcquired || null,
                life_span_ended:
                    lifeSpanEnded || null,
                status,
                mr_personnel_id:
                    mrPersonnelId
                        ? Number(
                            mrPersonnelId
                        )
                        : null,
                mr:
                    mrPersonnelId
                        ? mrPersonnelName(
                            mrPersonnelId
                        )
                        : null,
            }

    const payload = {
        asset_index:
            ictAssetEditingIndex.value,
        asset:
            assetPayload,
    }

    ictAssetEditProcessing.value = true

    router.put(
        `/dts/inventory/${ictAssetEditingItem.value.id}`,
        payload,
        {
            preserveScroll: true,
            preserveState: true,

            onSuccess: () => {
                const itemId =
                    ictAssetEditingItem.value?.id

                showIctAssetEditModal.value = false
                ictAssetEditingItem.value = null
                ictAssetEditingIndex.value = null
                ictAssetEditingOriginalPropertyNumber.value = ''
                ictAssetEditErrors.value = {}

                if (itemId) {
                    expandedIctItems.value = {
                        ...expandedIctItems.value,
                        [itemId]: true,
                    }
                }

                router.reload({
                    only: ['inventoryItems'],
                    preserveScroll: true,
                    preserveState: true,
                })
            },

            onError: (errors) => {
                ictAssetEditErrors.value = {
                    ...errors,
                }
            },

            onFinish: () => {
                ictAssetEditProcessing.value = false
            },
        }
    )
}


const saveFullEditItem = () => {
    if (!canManageInventory.value) {
        return
    }

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

    const isOtherItems =
        otherCategoryValues.includes(category)

    const location = String(
        fullEditForm.value.location || ''
    ).trim()

    const requiresItemCount =
        otherCategoryHasCount(category)

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
    const isIct = category === 'ict'

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

    if (
        ![
            'supplies',
            'ict',
            ...otherCategoryValues,
        ].includes(category)
    ) {
        fullEditErrors.value.category =
            'Select a valid category.'
    }

    if (!itemName) {
        fullEditErrors.value.item =
            'Item name is required.'
    }

    if (isOtherItems) {
        if (!location) {
            fullEditErrors.value.location =
                'Location is required.'
        }

        /*
         * Other Items use a category-aware Add button.
         *
         * Furniture/Fixtures:
         *   reveal one new Property Detail row, then Count +1.
         *
         * Emergency Kits / Token & Giveaways:
         *   reveal Quantity to Add.
         */
        if (
            requiresItemCount
            && showAddOtherCountField.value
        ) {
            if (
                isPropertyTrackedOtherCategory(
                    category
                )
            ) {
                const description =
                    String(
                        newOtherAssetForm.value
                            .description || ''
                    ).trim()

                const propertyNumber =
                    String(
                        newOtherAssetForm.value
                            .property_number || ''
                    ).trim()

                if (!description) {
                    fullEditErrors.value[
                        'new_other_asset.description'
                    ] =
                        'Description is required.'
                }

                if (!propertyNumber) {
                    fullEditErrors.value[
                        'new_other_asset.property_number'
                    ] =
                        'Property Number is required.'
                }

                if (propertyNumber) {
                    const duplicate =
                        ictAssetDetails(
                            original
                        ).some(
                            (asset) =>
                                String(
                                    asset.property_number
                                    || ''
                                )
                                    .trim()
                                    .toLowerCase()
                                ===
                                propertyNumber
                                    .toLowerCase()
                        )

                    if (duplicate) {
                        fullEditErrors.value[
                            'new_other_asset.property_number'
                        ] =
                            'Property Number already exists for this item.'
                    }
                }
            } else {
                const addCount =
                    Number(otherCountToAdd.value)

                if (
                    otherCountToAdd.value === ''
                    || otherCountToAdd.value === null
                    || otherCountToAdd.value === undefined
                    || !Number.isInteger(addCount)
                    || addCount < 1
                ) {
                    fullEditErrors.value.add_other_count =
                        'Enter how many items you want to add.'
                }
            }
        }
    } else {
        if (!unit) {
            fullEditErrors.value.unit =
                'Unit of measure is required.'
        }

        if (
            !Number.isInteger(inventoryYear)
            || inventoryYear < 2026
        ) {
            fullEditErrors.value.inventory_year =
                'Select a valid year from 2026 onwards.'
        }

        if (
            isSupplies
            && !quarters.length
        ) {
            fullEditErrors.value.quarters =
                'Select at least one applicable quarter.'
        }
    }

    if (isSupplies) {
        if (
            hasFixed
            && (!Number.isFinite(fixedValue) || fixedValue < 0)
        ) {
            fullEditErrors.value.fixed_value =
                'Enter a valid Fixed Value or leave it blank.'
        }
    }

    if (isSupplies) {
        quarters.forEach((quarter) => {
            const raw =
                fullEditForm.value
                    .quarter_stock?.[quarter]
                    ?.current

            const value =
                Number(raw)

            if (
                raw === ''
                || raw === null
                || raw === undefined
                || !Number.isInteger(value)
                || value < 0
            ) {
                fullEditErrors.value[
                    `quarter_stock.${quarter}.current`
                ] =
                    `Enter the current quantity for ${quarter.toUpperCase()}.`
            }
        })
    }


    if (isIct) {
        if (
            !hasCurrent
            || !Number.isInteger(currentValue)
            || currentValue < 0
        ) {
            fullEditErrors.value.currently_available =
                isIctMonthBased(unit)
                    ? 'Enter a valid number of month(s).'
                    : isIctYearBased(unit)
                        ? 'Enter a valid number of year(s).'
                        : 'Current Count is invalid.'
        }

        if (
            !isIctSubscription(unit)
            && showAddIctUnitFields.value
        ) {
            const newDescription =
                String(
                    newIctAssetForm.value
                        .description || ''
                ).trim()

            const newPropertyNumber =
                String(
                    newIctAssetForm.value
                        .property_number || ''
                ).trim()

            const newDateAcquired =
                normalizeIctDateInput(
                    newIctAssetForm.value
                        .date_acquired
                )

            const newLifeSpanEnded =
                normalizeIctDateInput(
                    newIctAssetForm.value
                        .life_span_ended
                )

            const newStatus =
                normalizeIctAssetStatus(
                    newIctAssetForm.value.status
                )

            if (!newDescription) {
                fullEditErrors.value[
                    'new_ict_asset.description'
                ] =
                    'Description is required.'
            }

            if (!newPropertyNumber) {
                fullEditErrors.value[
                    'new_ict_asset.property_number'
                ] =
                    'Property Number is required.'
            }

            if (!newStatus) {
                fullEditErrors.value[
                    'new_ict_asset.status'
                ] =
                    'Select Working or Returned.'
            }

            if (
                newIctAssetForm.value.date_acquired
                && !newDateAcquired
            ) {
                fullEditErrors.value[
                    'new_ict_asset.date_acquired'
                ] =
                    'Enter a valid Date Acquired.'
            }

            if (
                newIctAssetForm.value.life_span_ended
                && !newLifeSpanEnded
            ) {
                fullEditErrors.value[
                    'new_ict_asset.life_span_ended'
                ] =
                    'Enter a valid Life Span Ended date.'
            }

            if (
                !isValidIctDateRange(
                    newDateAcquired,
                    newLifeSpanEnded
                )
            ) {
                fullEditErrors.value[
                    'new_ict_asset.life_span_ended'
                ] =
                    'Life Span Ended cannot be earlier than Date Acquired.'
            }

            if (newPropertyNumber) {
                const duplicate =
                    ictAssetDetails(
                        fullEditingItem.value
                    ).some(
                        (asset) =>
                            String(
                                asset.property_number
                                || ''
                            )
                                .trim()
                                .toLowerCase()
                            ===
                            newPropertyNumber
                                .toLowerCase()
                    )

                if (duplicate) {
                    fullEditErrors.value[
                        'new_ict_asset.property_number'
                    ] =
                        'Property Number must be unique for this ICT item.'
                }
            }
        }
    }


    /*
     * Property Detail validation during EDIT.
     *
     * ICT equipment always requires complete Property Details.
     *
     * Furniture/Fixtures may contain legacy records that were created
     * before per-property tracking was introduced. Those records can have
     * a Count but no ict_assets at all. The edit form still generates blank
     * rows so the user can add Property Details later, but those generated
     * blank rows must NOT block ordinary edits such as Item Name, Count,
     * Location, or Remarks.
     *
     * Once a Furniture/Fixtures record already has Property Details, or the
     * user starts entering Property Details during this edit, validation
     * becomes strict again and every property row must be complete.
     */
    const existingPropertyAssets =
        normalizeIctAssets(
            original?.ict_assets
        )

    const editedPropertyAssets =
        normalizeIctAssets(
            fullEditForm.value.ict_assets
        )

    /*
     * Existing Furniture/Fixtures property rows are NOT bulk-edited
     * from the item modal. Each row has its own Edit button in the
     * accordion, so item-level Save must not validate or rewrite them.
     */

    const quarterStockPayload = {}

    if (isSupplies) {
        quarters.forEach((quarter) => {
            const existing =
                fullEditForm.value
                    .quarter_stock?.[quarter]
                || {}

            quarterStockPayload[quarter] = {
                ...existing,
                current:
                    Number(
                        existing.current
                    ),
                remarks:
                    String(
                        existing.remarks
                        ?? ''
                    ).trim(),
            }
        })
    }

    const aggregateQuarterCurrent =
        Object.values(
            quarterStockPayload
        ).reduce(
            (total, entry) =>
                total
                + (
                    Number.isFinite(
                        Number(entry.current)
                    )
                        ? Number(entry.current)
                        : 0
                ),
            0
        )

    const effectiveItemName =
        itemName

    const duplicateExists =
        normalizedInventoryItems.value.some((item) => {
            if (
                Number(item.id) === Number(original.id)
                || item.category !== category
            ) {
                return false
            }

            if (
                String(item.item || '')
                    .trim()
                    .toLowerCase()
                !== effectiveItemName.toLowerCase()
            ) {
                return false
            }

            if (isIct) {
                return (
                    Number(item.inventory_year)
                        === inventoryYear
                    &&
                    String(item.unit || '')
                        .trim()
                        .toUpperCase()
                        === unit
                )
            }

            if (isOtherItems) {
                return String(item.location || '')
                    .trim()
                    .toLowerCase()
                    === location.toLowerCase()
            }

            return (
                Number(item.inventory_year) === inventoryYear
                &&
                String(item.unit || '')
                    .trim()
                    .toUpperCase()
                    === unit
            )
        })

    if (duplicateExists) {
        if (isIct) {
            fullEditErrors.value.item =
                `This ICT item and unit already exist for ${inventoryYear}.`
        } else {
            fullEditErrors.value.item =
                isOtherItems
                    ? 'This item already exists in the same location.'
                    : `This item and unit already exist for ${inventoryYear}.`
        }
    }

    if (Object.keys(fullEditErrors.value).length) {
        return
    }

    const payload = {
        category,
        item: effectiveItemName,
        description: null,
        remarks:
            String(fullEditForm.value.remarks || '').trim()
            || null,
    }

    if (isOtherItems) {
        payload.location = location

        if (
            requiresItemCount
            && showAddOtherCountField.value
        ) {
            if (
                isPropertyTrackedOtherCategory(
                    category
                )
            ) {
                payload.new_other_asset = {
                    description:
                        String(
                            newOtherAssetForm.value
                                .description || ''
                        ).trim(),

                    property_number:
                        String(
                            newOtherAssetForm.value
                                .property_number || ''
                        ).trim(),

                    current_user:
                        String(
                            newOtherAssetForm.value
                                .current_user || ''
                        ).trim()
                        || null,
                }
            } else {
                payload.add_other_count =
                    Number(otherCountToAdd.value)
            }
        }

        /*
         * Existing Furniture/Fixtures property rows are preserved.
         * Only new_other_asset is sent when Add Unit is used.
         * Existing rows are edited individually from the accordion.
         */
    } else if (isIct) {
        payload.location = null
        payload.unit = unit
        payload.inventory_year = inventoryYear
        payload.fixed_value = null

        if (isIctSubscription(unit)) {
            payload.currently_available =
                currentValue
            payload.ict_assets = []
        } else {
            /*
             * Existing Property Details are edited one row at a time
             * from the accordion. The Item Edit modal can optionally
             * add one new ICT unit at a time.
             */
            if (showAddIctUnitFields.value) {
                const mrPersonnelId =
                    String(
                        newIctAssetForm.value
                            .mr_personnel_id || ''
                    ).trim()

                payload.new_ict_asset = {
                    description:
                        String(
                            newIctAssetForm.value
                                .description || ''
                        ).trim(),

                    accessories:
                        String(
                            newIctAssetForm.value
                                .accessories || ''
                        ).trim()
                        || null,

                    property_number:
                        String(
                            newIctAssetForm.value
                                .property_number || ''
                        ).trim(),

                    current_user:
                        String(
                            newIctAssetForm.value
                                .current_user || ''
                        ).trim()
                        || null,

                    date_acquired:
                        normalizeIctDateInput(
                            newIctAssetForm.value
                                .date_acquired
                        )
                        || null,

                    life_span_ended:
                        normalizeIctDateInput(
                            newIctAssetForm.value
                                .life_span_ended
                        )
                        || null,

                    status:
                        normalizeIctAssetStatus(
                            newIctAssetForm.value
                                .status
                        )
                        || 'working',

                    mr_personnel_id:
                        mrPersonnelId
                            ? Number(
                                mrPersonnelId
                            )
                            : null,

                    mr:
                        mrPersonnelId
                            ? mrPersonnelName(
                                mrPersonnelId
                            )
                            : null,
                }
            }
        }
    } else {
        payload.location = null
        payload.unit = unit
        payload.inventory_year = inventoryYear
        payload.quarters = quarters
        payload.fixed_value = fixedValue
        payload.quarter_stock =
            quarterStockPayload
        payload.currently_available =
            aggregateQuarterCurrent
    }

    router.put(
        `/dts/inventory/${original.id}`,
        payload,
        {
            preserveScroll: true,

            onSuccess: () => {
                if (isOtherItems) {
                    activeTab.value = 'other'
                    otherCategoryFilter.value =
                        otherFilterValueForCategory(
                            category
                        )
                } else {
                    activeTab.value = category
                    yearFilter.value = inventoryYear
                }

                quarterFilter.value = 'all'
                unitFilter.value = 'all'
                search.value = ''
                currentPage.value = 1

                if (
                    isIct
                    && !isIctSubscription(unit)
                    && showAddIctUnitFields.value
                ) {
                    expandedIctItems.value = {
                        ...expandedIctItems.value,
                        [original.id]: true,
                    }
                }

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

const isNormalIctAssetItem = (item) => {
    const category =
        String(item?.category || '')
            .trim()
            .toLowerCase()

    return (
        (
            category === 'ict'
            && !isIctSubscription(item)
        )
        || isPropertyTrackedOtherCategory(
            category
        )
    )
}

const ictAvailableAssets = (item) => {
    if (!isNormalIctAssetItem(item)) {
        return []
    }

    return ictAssetDetails(item)
        .filter((asset) => {
            const propertyNumber =
                String(
                    asset?.property_number ?? ''
                ).trim()

            const currentUser =
                String(
                    asset?.current_user ?? ''
                ).trim()

            return Boolean(propertyNumber)
                && !currentUser
        })
}

const ictAvailableCount = (item) =>
    ictAvailableAssets(item).length

const canReleaseInventoryItem = (item) => {
    if (isNormalIctAssetItem(item)) {
        return ictAvailableCount(item) > 0
    }

    const available =
        currentAvailableValue(item)

    return (
        available !== null
        && Number(available) > 0
    )
}

const releaseCategory = computed(() =>
    String(
        releasingItem.value?.category || ''
    )
        .trim()
        .toLowerCase()
)

const releaseIsSupplies = computed(() =>
    releaseCategory.value === 'supplies'
)

const releaseIsIct = computed(() =>
    releaseCategory.value === 'ict'
)

const releaseIsIctAsset = computed(() =>
    isNormalIctAssetItem(
        releasingItem.value
    )
)

const releaseAvailableProperties = computed(() =>
    releaseIsIctAsset.value
        ? ictAvailableAssets(
            releasingItem.value
        )
        : []
)

const releaseIsCountedOther = computed(() =>
    [
        'furniture',
        'fixtures',
        'token_giveaways',
    ].includes(
        releaseCategory.value
    )
)

const releaseQuantityLabel = computed(() => {
    if (releaseIsIct.value) {
        return ictQuantityLabel(
            releasingItem.value
        )
    }

    return 'Quantity'
})

const releaseCurrentLabel = computed(() => {
    if (releaseIsSupplies.value) {
        return 'Currently Available in SPD'
    }

    if (releaseIsIctAsset.value) {
        return 'Available'
    }

    if (releaseIsIct.value) {
        return `${ictQuantityLabel(
            releasingItem.value
        )} Available`
    }

    return 'Count Available'
})

const releaseActionLabel = computed(() => {
    if (releaseIsIct.value) {
        if (
            isIctMonthBased(
                releasingItem.value
            )
        ) {
            return 'Month(s) to Release'
        }

        if (
            isIctYearBased(
                releasingItem.value
            )
        ) {
            return 'Year(s) to Release'
        }
    }

    return 'Quantity to Release'
})

const releaseUsesQuarterStock = computed(() =>
    releaseCategory.value === 'supplies'
)

const releaseSelectedQuarter = computed(() => {
    if (!releaseUsesQuarterStock.value) {
        return 'all'
    }

    return latestQuarterForItem(
        releasingItem.value
    )
})

const isHistoricalQuarterView = (item) => {
    const category =
        String(item?.category || '')
            .trim()
            .toLowerCase()

    if (
        category !== 'supplies'
        || quarterFilter.value === 'all'
    ) {
        return false
    }

    const latest =
        latestQuarterForItem(item)

    return Boolean(
        latest
        && quarterFilter.value !== latest
    )
}

const canReleaseInCurrentView = (item) => {
    return !isHistoricalQuarterView(
        item
    )
}

const releaseTotalReleased = computed(() => {
    return quantityReleasedValue(
        releasingItem.value,
        releaseSelectedQuarter.value
            || 'all'
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
    if (releaseIsIctAsset.value) {
        return ictAvailableCount(
            releasingItem.value
        )
    }

    return currentAvailableValue(
        releasingItem.value,
        releaseSelectedQuarter.value
            || 'all'
    ) ?? 0
})

const releaseQuantity = computed(() => {
    if (releaseIsIctAsset.value) {
        return String(
            releaseItemForm.value
                .releasePropertyNumber
            || ''
        ).trim()
            ? 1
            : 0
    }

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
    if (releaseIsIctAsset.value) {
        return (
            Number(
                releasingItem.value
                    ?.tracked_released
                ?? 0
            )
            + (
                releaseQuantity.value > 0
                    ? 1
                    : 0
            )
        )
    }

    if (releaseUsesQuarterStock.value) {
        return (
            releaseTotalReleased.value
            + releaseQuantity.value
        )
    }

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
    if (!canManageInventory.value) {
        return
    }

    if (!item?.id) {
        return
    }

    const category =
        String(item.category || '')
            .trim()
            .toLowerCase()

    const canRelease =
        [
            'supplies',
            'ict',
            'furniture',
            'fixtures',
            'emergency_kits',
            'token_giveaways',
        ].includes(category)

    if (
        !canRelease
        || !canReleaseInventoryItem(item)
    ) {
        if (
            isNormalIctAssetItem(item)
            && ictAvailableCount(item) <= 0
        ) {
            window.alert(
                'No available ICT property is currently unassigned.'
            )
        }

        return
    }

    const itemQuarters =
        inferredItemQuarters(item)

    if (
        category === 'supplies'
        && itemQuarters.length > 1
        && !hasQuarterStock(item)
    ) {
        window.alert(
            'This is a legacy multi-quarter item. Open Edit first and set the remaining quantity for each quarter before releasing.'
        )

        return
    }

    if (
        category === 'supplies'
        && isHistoricalQuarterView(item)
    ) {
        const latest =
            latestQuarterForItem(item)

        window.alert(
            `${quarterFilter.value.toUpperCase()} is already a historical quarter. Releases are automatically deducted from ${latest.toUpperCase()}, the latest active quarter.`
        )

        return
    }

    releasingItem.value = item

    /*
     * Every release is a NEW transaction.
     * Supplies automatically deduct from the latest active quarter.
     * ICT and Other Items use their global Count/Duration balance.
     */
    releaseItemForm.value = {
        releaseQuantity: '',
        releasePropertyNumber: '',
        releaseDestination: '',
        releaseMrPersonnelId: '',
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
        releasePropertyNumber: '',
        releaseDestination: '',
        releaseMrPersonnelId: '',
        remarks: '',
    }

    releaseItemErrors.value = {}
}

const syncReleaseMrHolder = () => {
    if (!releaseIsIctAsset.value) {
        return
    }

    const mrName =
        mrPersonnelName(
            releaseItemForm.value
                .releaseMrPersonnelId
        )

    if (
        mrName
        && !String(
            releaseItemForm.value
                .releaseDestination || ''
        ).trim()
    ) {
        releaseItemForm.value
            .releaseDestination =
                mrName
    }
}

const saveReleaseItem = () => {
    if (!canManageInventory.value) {
        return
    }

    const item = releasingItem.value

    if (!item?.id) {
        return
    }

    releaseItemErrors.value = {}

    const releaseQuarter =
        releaseUsesQuarterStock.value
            ? releaseSelectedQuarter.value
            : ''

    if (
        releaseUsesQuarterStock.value
        && !releaseQuarter
    ) {
        releaseItemErrors.value.releaseQuantity =
            'No active quarter is available for this item.'

        return
    }

    const releasePropertyNumber =
        String(
            releaseItemForm.value
                .releasePropertyNumber
            || ''
        ).trim()

    const releaseQuantity =
        releaseIsIctAsset.value
            ? 1
            : Number(
                releaseItemForm.value
                    .releaseQuantity
            )

    if (releaseIsIctAsset.value) {
        const propertyStillAvailable =
            releaseAvailableProperties.value
                .some(
                    (asset) =>
                        String(
                            asset.property_number
                            ?? ''
                        ).trim()
                            === releasePropertyNumber
                )

        if (
            !releasePropertyNumber
            || !propertyStillAvailable
        ) {
            releaseItemErrors.value
                .releasePropertyNumber =
                    'Select an available Property Number.'

            return
        }
    } else {
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
                `Only ${releaseCurrentAvailable.value} available.`

            return
        }
    }

    const releaseDestination =
        String(
            releaseItemForm.value.releaseDestination
            || ''
        ).trim()

    const releaseMrPersonnelId =
        String(
            releaseItemForm.value
                .releaseMrPersonnelId
            || ''
        ).trim()

    if (
        releaseIsIctAsset.value
        && !releaseMrPersonnelId
    ) {
        releaseItemErrors.value.releaseMrPersonnelId =
            'Select the MR Holder.'

        return
    }

    /*
     * Supplies do not require a destination.
     * ICT and counted Other Items still do so History can show
     * where those released items went.
     */
    if (
        !releaseIsSupplies.value
        && !releaseDestination
    ) {
        releaseItemErrors.value.releaseDestination =
            releaseIsIctAsset.value
                ? 'Enter the Current User / Released To.'
                : 'Enter where the released item went.'

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

            release_quarter:
                releaseUsesQuarterStock.value
                    ? releaseQuarter
                    : null,

            release_destination:
                releaseIsSupplies.value
                    ? null
                    : releaseDestination,

            release_property_number:
                releaseIsIctAsset.value
                    ? releasePropertyNumber
                    : null,

            release_mr_personnel_id:
                releaseIsIctAsset.value
                    ? Number(
                        releaseMrPersonnelId
                    )
                    : null,

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
                if (
                    releaseIsIctAsset.value
                ) {
                    const nextAssets =
                        ictEditorAssetRows(
                            item.ict_assets
                        ).map((asset) => {
                            if (
                                String(
                                    asset.property_number
                                    ?? ''
                                ).trim()
                                !== releasePropertyNumber
                            ) {
                                return asset
                            }

                            return {
                                ...asset,
                                current_user:
                                    releaseDestination,
                            }
                        })

                    updateLocalInventoryItem(
                        item.id,
                        {
                            ict_assets:
                                nextAssets,
                            tracked_released:
                                Number(
                                    item.tracked_released
                                    ?? 0
                                ) + 1,
                        }
                    )
                } else if (
                    releaseUsesQuarterStock.value
                    && hasQuarterStock(item)
                ) {
                    const nextQuarterStock =
                        normalizeQuarterStock(
                            item.quarter_stock
                        )

                    const quarterEntry = {
                        ...(
                            nextQuarterStock[
                                releaseQuarter
                            ]
                            || {
                                opening:
                                    releaseCurrentAvailable.value,
                                current:
                                    releaseCurrentAvailable.value,
                                released:
                                    releaseTotalReleased.value,
                            }
                        ),
                    }

                    quarterEntry.current =
                        nextCurrent

                    quarterEntry.released =
                        Number(
                            quarterEntry.released
                            ?? 0
                        )
                        + releaseQuantity

                    quarterEntry.opening =
                        Number(
                            quarterEntry.opening
                            ?? (
                                quarterEntry.current
                                + quarterEntry.released
                            )
                        )

                    nextQuarterStock[
                        releaseQuarter
                    ] = quarterEntry

                    const aggregateCurrent =
                        Object.values(
                            nextQuarterStock
                        ).reduce(
                            (total, entry) =>
                                total
                                + Number(
                                    entry.current
                                    ?? 0
                                ),
                            0
                        )

                    updateLocalInventoryItem(
                        item.id,
                        {
                            currently_available:
                                aggregateCurrent,
                            quarter_stock:
                                nextQuarterStock,
                            tracked_released:
                                Object.values(
                                    nextQuarterStock
                                ).reduce(
                                    (total, entry) =>
                                        total
                                        + Number(
                                            entry.released
                                            ?? 0
                                        ),
                                    0
                                ),
                        }
                    )
                } else {
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
                }

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


/*
|--------------------------------------------------------------------------
| GENERATE INVENTORY REPORT
|--------------------------------------------------------------------------
|
| Uses the CURRENT Inventory filters:
| - active category/tab
| - Inventory Year
| - Quarter
| - Unit
| - Search
|
| Opens a clean print view that can be printed or saved as PDF.
|
*/

const escapeReportHtml = (value) => {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;')
}

const reportNumber = (value) => {
    if (
        value === null
        || value === undefined
        || String(value).trim() === ''
    ) {
        return '—'
    }

    const number = Number(value)

    return Number.isFinite(number)
        ? number.toLocaleString()
        : escapeReportHtml(value)
}

const reportQuarterText = (item) => {
    const quarters =
        inferredItemQuarters(item)

    return quarters.length
        ? quarters
            .map((quarter) =>
                quarter.toUpperCase()
            )
            .join(', ')
        : '—'
}

const generateInventoryReport = () => {
    const rows =
        filteredItems.value

    if (!rows.length) {
        window.alert(
            'No inventory records match the selected report filters.'
        )
        return
    }

    const categoryLabel =
        activeTab.value === 'supplies'
            ? 'Supplies'
            : activeTab.value === 'ict'
                ? 'ICT'
                : `Other Items - ${currentOtherCategoryLabel.value}`

    const quarterLabel =
        quarterFilter.value === 'all'
            ? 'All Quarters'
            : quarterFilter.value.toUpperCase()

    const unitLabel =
        unitFilter.value === 'all'
            ? 'All Units'
            : unitFilter.value

    const searchLabel =
        search.value.trim()
            ? escapeReportHtml(
                search.value.trim()
            )
            : 'None'

    const generatedAt =
        new Date().toLocaleString(
            'en-PH',
            {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            }
        )

    /*
    |----------------------------------------------------------------------
    | REPORT SUMMARY
    |----------------------------------------------------------------------
    |
    | Totals are grouped BY UNIT so unlike measurements such as BOX,
    | PIECE, REAM, PACK, etc. are never incorrectly added together.
    |
    */
    const unitSummaryMap = new Map()

    rows.forEach((item) => {
        const unit =
            String(item.unit || 'UNSPECIFIED')
                .trim()
                .toUpperCase()
                || 'UNSPECIFIED'

        if (!unitSummaryMap.has(unit)) {
            unitSummaryMap.set(
                unit,
                {
                    unit,
                    itemCount: 0,
                    fixedTotal: 0,
                    releasedTotal: 0,
                    currentTotal: 0,
                    fixedCount: 0,
                    currentCount: 0,
                }
            )
        }

        const summary =
            unitSummaryMap.get(unit)

        summary.itemCount += 1

        if (activeTab.value === 'supplies') {
            const fixed =
                item.fixed !== null
                && item.fixed !== undefined
                    ? Number(item.fixed)
                    : (
                        item.fixed_value !== null
                        && item.fixed_value !== undefined
                            ? Number(item.fixed_value)
                            : null
                    )

            const released =
                Number(
                    quantityReleasedValue(item)
                )

            const current =
                currentAvailableValue(item)

            if (
                fixed !== null
                && Number.isFinite(fixed)
            ) {
                summary.fixedTotal += fixed
                summary.fixedCount += 1
            }

            if (Number.isFinite(released)) {
                summary.releasedTotal += released
            }

            if (
                current !== null
                && current !== undefined
                && Number.isFinite(Number(current))
            ) {
                summary.currentTotal += Number(current)
                summary.currentCount += 1
            }
        } else if (activeTab.value === 'ict') {
            const quantity =
                currentAvailableValue(item)

            if (
                quantity !== null
                && Number.isFinite(Number(quantity))
            ) {
                summary.currentTotal += Number(quantity)
                summary.currentCount += 1
            }
        }
    })

    const unitSummaries =
        Array.from(
            unitSummaryMap.values()
        ).sort((a, b) =>
            a.unit.localeCompare(b.unit)
        )

    const summaryUnitCount =
        unitSummaries.length

    const summaryLocationCount =
        new Set(
            rows
                .map((item) =>
                    String(item.location || '').trim()
                )
                .filter(Boolean)
        ).size


    const summaryOtherItemCount =
        rows.reduce(
            (total, item) =>
                total
                + Math.max(
                    0,
                    Number(item.currently_available || 0)
                ),
            0
        )

    const suppliesSummaryRows =
        unitSummaries
            .map((summary) => {
                const accounted =
                    summary.currentTotal
                    + summary.releasedTotal

                const remainingPercent =
                    accounted > 0
                        ? Math.round(
                            (
                                summary.currentTotal
                                / accounted
                            )
                            * 100
                        )
                        : null

                return `
                    <tr>
                        <td>${escapeReportHtml(summary.unit)}</td>
                        <td class="number">${summary.itemCount.toLocaleString()}</td>
                        <td class="number">${
                            summary.fixedCount > 0
                                ? summary.fixedTotal.toLocaleString()
                                : '—'
                        }</td>
                        <td class="number">${summary.releasedTotal.toLocaleString()}</td>
                        <td class="number">${summary.currentTotal.toLocaleString()}</td>
                        <td class="number">${
                            remainingPercent !== null
                                ? `${remainingPercent}%`
                                : '—'
                        }</td>
                    </tr>
                `
            })
            .join('')

    const ictSummaryRows =
        unitSummaries
            .map((summary) => `
                <tr>
                    <td>${escapeReportHtml(summary.unit)}</td>
                    <td class="number">${summary.itemCount.toLocaleString()}</td>
                    <td class="number">${
                        summary.currentCount > 0
                            ? summary.currentTotal.toLocaleString()
                            : '—'
                    }</td>
                </tr>
            `)
            .join('')

    const summaryHtml =
        activeTab.value === 'supplies'
            ? `
                <section class="summary-section">
                    <div class="summary-heading">
                        <div>
                            <p class="summary-eyebrow">Report Summary</p>
                            <h2>Stock Summary by Unit</h2>
                        </div>

                        <div class="summary-cards">
                            <div class="summary-card">
                                <span class="summary-card-label">Line Items</span>
                                <strong>${rows.length.toLocaleString()}</strong>
                            </div>

                            <div class="summary-card">
                                <span class="summary-card-label">Units Represented</span>
                                <strong>${summaryUnitCount.toLocaleString()}</strong>
                            </div>
                        </div>
                    </div>

                    <p class="summary-note">
                        Stock quantities are summarized per unit of measure so BOX, PIECE, REAM, PACK, and other units are not combined incorrectly.
                    </p>

                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Unit</th>
                                <th class="number">Line Items</th>
                                <th class="number">Fixed Value</th>
                                <th class="number">Quantity Released</th>
                                <th class="number">Currently Available</th>
                                <th class="number">Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${suppliesSummaryRows}
                        </tbody>
                    </table>
                </section>
            `
            : activeTab.value === 'ict'
                ? `
                    <section class="summary-section">
                        <div class="summary-heading">
                            <div>
                                <p class="summary-eyebrow">Report Summary</p>
                                <h2>ICT Summary</h2>
                            </div>

                            <div class="summary-cards">
                                <div class="summary-card">
                                    <span class="summary-card-label">Line Items</span>
                                    <strong>${rows.length.toLocaleString()}</strong>
                                </div>

                                <div class="summary-card">
                                    <span class="summary-card-label">Item Names Represented</span>
                                    <strong>${summaryUnitCount.toLocaleString()}</strong>
                                </div>
                            </div>
                        </div>

                        <table class="summary-table compact-summary">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th class="number">Line Items</th>
                                    <th class="number">Total Count / Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${ictSummaryRows}
                            </tbody>
                        </table>
                    </section>
                `
                : `
                    <section class="summary-section">
                        <div class="summary-heading">
                            <div>
                                <p class="summary-eyebrow">Report Summary</p>
                                <h2>${escapeReportHtml(categoryLabel)} Summary</h2>
                            </div>

                            <div class="summary-cards">
                                <div class="summary-card">
                                    <span class="summary-card-label">Line Items</span>
                                    <strong>${rows.length.toLocaleString()}</strong>
                                </div>

                                ${
                                    currentOtherCategoryHasCount.value
                                        ? `
                                            <div class="summary-card">
                                                <span class="summary-card-label">Total Count</span>
                                                <strong>${summaryOtherItemCount.toLocaleString()}</strong>
                                            </div>

                                            ${
                                                currentOtherCategoryIsAssetTracked.value
                                                    ? `
                                                        <div class="summary-card">
                                                            <span class="summary-card-label">Total Available</span>
                                                            <strong>${rows.reduce((total, item) => total + ictAvailableCount(item), 0).toLocaleString()}</strong>
                                                        </div>
                                                    `
                                                    : ''
                                            }
                                        `
                                        : ''
                                }

                                <div class="summary-card">
                                    <span class="summary-card-label">Locations Represented</span>
                                    <strong>${summaryLocationCount.toLocaleString()}</strong>
                                </div>
                            </div>
                        </div>
                    </section>
                `

    const suppliesHeader = `
        <tr>
            <th>Item</th>
            <th>Unit</th>
            <th>Quarter(s)</th>
            <th class="number">Fixed Value</th>
            <th class="number">Quantity Released</th>
            <th class="number">Currently Available in SPD</th>
            <th>Remarks</th>
        </tr>
    `

    const ictHeader = `
        <tr>
            <th>Item Name</th>
            <th class="number">Count / Duration</th>
            <th class="number">Available</th>
            <th>Remarks</th>
        </tr>
    `

    const otherHeader = currentOtherCategoryIsAssetTracked.value
        ? `
            <tr>
                <th>Item</th>
                <th class="number">Count</th>
                <th class="number">Available</th>
                <th>Location</th>
                <th>Remarks</th>
            </tr>
        `
        : `
            <tr>
                <th>Item</th>
                <th class="number">Count</th>
                <th>Location</th>
                <th>Remarks</th>
            </tr>
        `

    const reportRows = rows
        .map((item) => {
            const itemName =
                escapeReportHtml(
                    item.item || '—'
                )

            const unit =
                escapeReportHtml(
                    item.unit || '—'
                )

            const quarters =
                escapeReportHtml(
                    reportQuarterText(item)
                )

            const remarks =
                escapeReportHtml(
                    String(
                        item.remarks || ''
                    ).trim() || '—'
                )

            if (activeTab.value === 'supplies') {
                const fixed =
                    item.fixed !== null
                    && item.fixed !== undefined
                        ? reportNumber(item.fixed)
                        : (
                            item.fixed_value !== null
                            && item.fixed_value !== undefined
                                ? reportNumber(
                                    item.fixed_value
                                )
                                : '—'
                        )

                const released =
                    reportNumber(
                        quantityReleasedValue(item)
                    )

                const current =
                    reportNumber(
                        currentAvailableValue(item)
                    )

                return `
                    <tr>
                        <td class="item">${itemName}</td>
                        <td>${unit}</td>
                        <td>${quarters}</td>
                        <td class="number">${fixed}</td>
                        <td class="number">${released}</td>
                        <td class="number current">${current}</td>
                        <td class="remarks">${remarks}</td>
                    </tr>
                `
            }

            if (activeTab.value === 'other') {
                const location =
                    escapeReportHtml(
                        String(item.location || '').trim()
                        || '—'
                    )

                const itemCount =
                    reportNumber(
                        item.currently_available
                    )

                return currentOtherCategoryIsAssetTracked.value
                    ? `
                        <tr>
                            <td class="item">${itemName}</td>
                            <td class="number">${itemCount}</td>
                            <td class="number">${ictAvailableCount(item)}</td>
                            <td>${location}</td>
                            <td class="remarks">${remarks}</td>
                        </tr>
                    `
                    : `
                        <tr>
                            <td class="item">${itemName}</td>
                            <td class="number">${itemCount}</td>
                            <td>${location}</td>
                            <td class="remarks">${remarks}</td>
                        </tr>
                    `
            }

            const ictQuantity =
                currentAvailableValue(item)

            const ictQuantityText =
                ictQuantity === null
                    ? '—'
                    : isIctMonthBased(item)
                        ? `${reportNumber(ictQuantity)} ${ictQuantity === 1 ? 'Month' : 'Months'}`
                        : isIctYearBased(item)
                            ? `${reportNumber(ictQuantity)} ${ictQuantity === 1 ? 'Year' : 'Years'}`
                            : reportNumber(ictQuantity)

            const ictItemName =
                escapeReportHtml(
                    String(
                        item.item || ''
                    ).trim() || '—'
                )

            return `
                <tr>
                    <td class="item">${ictItemName}</td>
                    <td class="number">${ictQuantityText}</td>
                    <td class="number">${
                        isIctSubscription(item)
                            ? '—'
                            : ictAvailableCount(item)
                    }</td>
                    <td class="remarks">${remarks}</td>
                </tr>
            `
        })
        .join('')

    const reportWindow =
        window.open(
            '',
            '_blank',
            'width=1400,height=900'
        )

    if (!reportWindow) {
        window.alert(
            'The report window was blocked. Please allow pop-ups for this site and try again.'
        )
        return
    }

    const reportFiltersHtml =
        activeTab.value === 'other'
            ? `
                <section class="filters">
                    <div class="filter">
                        <span class="filter-label">Category</span>
                        <span class="filter-value">
                            ${escapeReportHtml(currentOtherCategoryLabel.value)}
                        </span>
                    </div>

                    <div class="filter">
                        <span class="filter-label">Search</span>
                        <span class="filter-value">${searchLabel}</span>
                    </div>

                    <div class="filter">
                        <span class="filter-label">Generated</span>
                        <span class="filter-value">
                            ${escapeReportHtml(generatedAt)}
                        </span>
                    </div>
                </section>
            `
            : activeTab.value === 'supplies'
                ? `
                    <section class="filters">
                        <div class="filter">
                            <span class="filter-label">Inventory Year</span>
                            <span class="filter-value">
                                ${escapeReportHtml(yearFilter.value)}
                            </span>
                        </div>

                        <div class="filter">
                            <span class="filter-label">Quarter</span>
                            <span class="filter-value">
                                ${escapeReportHtml(quarterLabel)}
                            </span>
                        </div>

                        <div class="filter">
                            <span class="filter-label">Unit</span>
                            <span class="filter-value">
                                ${escapeReportHtml(unitLabel)}
                            </span>
                        </div>

                        <div class="filter">
                            <span class="filter-label">Search</span>
                            <span class="filter-value">${searchLabel}</span>
                        </div>

                        <div class="filter">
                            <span class="filter-label">Generated</span>
                            <span class="filter-value">
                                ${escapeReportHtml(generatedAt)}
                            </span>
                        </div>
                    </section>
                `
                : `
                    <section class="filters">
                        <div class="filter">
                            <span class="filter-label">Inventory Year</span>
                            <span class="filter-value">
                                ${escapeReportHtml(yearFilter.value)}
                            </span>
                        </div>

                        <div class="filter">
                            <span class="filter-label">Unit</span>
                            <span class="filter-value">
                                ${escapeReportHtml(unitLabel)}
                            </span>
                        </div>

                        <div class="filter">
                            <span class="filter-label">Search</span>
                            <span class="filter-value">${searchLabel}</span>
                        </div>

                        <div class="filter">
                            <span class="filter-label">Generated</span>
                            <span class="filter-value">
                                ${escapeReportHtml(generatedAt)}
                            </span>
                        </div>
                    </section>
                `

    const reportHtml = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Inventory Report - ${escapeReportHtml(categoryLabel)}${activeTab.value === 'other' ? '' : ` ${yearFilter.value}`}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 28px;
            background: #ffffff;
            color: #0f172a;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            font-size: 11px;
        }

        .report {
            max-width: 1500px;
            margin: 0 auto;
        }

        .header {
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 14px;
            margin-bottom: 14px;
        }

        .agency {
            margin: 0;
            color: #475569;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 5px 0 0;
            font-size: 22px;
            line-height: 1.15;
            color: #0f172a;
        }

        .subtitle {
            margin: 5px 0 0;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .filters {
            display: grid;
            grid-template-columns:
                repeat(5, minmax(0, 1fr));
            gap: 8px;
            margin: 0 0 14px;
        }

        .filter {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 8px 10px;
        }

        .filter-label {
            display: block;
            color: #64748b;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .filter-value {
            display: block;
            margin-top: 3px;
            color: #0f172a;
            font-size: 10px;
            font-weight: 700;
            word-break: break-word;
        }

        .summary-section {
            margin: 0 0 18px;
            padding: 14px;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            background: #f8fbff;
        }

        .summary-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 10px;
        }

        .summary-eyebrow {
            margin: 0 0 3px;
            color: #2563eb;
            font-size: 8px;
            font-weight: 800;
            letter-spacing: 0.10em;
            text-transform: uppercase;
        }

        .summary-heading h2 {
            margin: 0;
            color: #0f172a;
            font-size: 15px;
            line-height: 1.2;
        }

        .summary-cards {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .summary-card {
            min-width: 100px;
            padding: 7px 10px;
            border: 1px solid #dbeafe;
            border-radius: 6px;
            background: #ffffff;
            text-align: center;
        }

        .summary-card-label {
            display: block;
            color: #64748b;
            font-size: 7px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .summary-card strong {
            display: block;
            margin-top: 2px;
            color: #1d4ed8;
            font-size: 15px;
            line-height: 1.1;
        }

        .summary-note {
            margin: 0 0 9px;
            color: #64748b;
            font-size: 8px;
            line-height: 1.4;
        }

        .summary-table {
            margin-bottom: 0;
            background: #ffffff;
            font-size: 9px;
        }

        .summary-table th,
        .summary-table td {
            padding: 5px 7px;
        }

        .compact-summary {
            max-width: 520px;
        }

        .detail-heading {
            margin: 0 0 7px;
            color: #0f172a;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #94a3b8;
            padding: 7px 8px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        th {
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 9px;
            font-weight: 800;
            text-align: left;
            text-transform: uppercase;
        }

        td.item {
            font-weight: 700;
        }

        th.number,
        td.number {
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        td.current {
            font-weight: 800;
        }

        td.remarks {
            white-space: pre-wrap;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-top: 12px;
            padding-top: 9px;
            border-top: 1px solid #cbd5e1;
            color: #64748b;
            font-size: 9px;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 14px;
        }

        .print-button {
            border: 0;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            padding: 9px 14px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        @media print {
            body {
                padding: 0;
                font-size: 9px;
            }

            .print-actions {
                display: none !important;
            }

            .report {
                max-width: none;
            }

            thead {
                display: table-header-group;
            }

            tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .filters {
                grid-template-columns:
                    repeat(5, minmax(0, 1fr));
            }

            .summary-section {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .summary-heading {
                margin-bottom: 7px;
            }
        }
    </style>
</head>

<body>
    <div class="report">
        <div class="print-actions">
            <button
                type="button"
                class="print-button"
                onclick="window.print()"
            >
                Print / Save as PDF
            </button>
        </div>

        <header class="header">
            <p class="agency">
                Department of Science and Technology · Document Tracking System
            </p>

            <h1>
                Inventory Monitoring Report
            </h1>

            <p class="subtitle">
                ${escapeReportHtml(categoryLabel)}
            </p>
        </header>

        ${reportFiltersHtml}


        ${summaryHtml}

        <h2 class="detail-heading">
            Detailed Inventory
        </h2>

        <table>
            <thead>
                ${
                    activeTab.value === 'supplies'
                        ? suppliesHeader
                        : activeTab.value === 'ict'
                            ? ictHeader
                            : otherHeader
                }
            </thead>

            <tbody>
                ${reportRows}
            </tbody>
        </table>

        <footer class="footer">
            <span>
                Inventory Monitoring · ${escapeReportHtml(categoryLabel)}
            </span>

            <span>
                Generated ${escapeReportHtml(generatedAt)}
            </span>
        </footer>
    </div>
</body>
</html>
    `

    reportWindow.document.open()
    reportWindow.document.write(
        reportHtml
    )
    reportWindow.document.close()
    reportWindow.focus()
}

</script>

<template>
    <Head title="Inventory" />

    <div
        class="inventory-comfort-theme min-h-screen bg-[#e8eef5]"
    >
        <main
            class="mx-auto max-w-[1700px] px-4 py-5 sm:px-6 lg:px-8"
        >
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

                    <div
                        class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end"
                    >
                        <button
                            v-if="!['reconciliation', 'returned'].includes(activeTab)"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-blue-200 bg-blue-50 px-5 text-sm font-black text-blue-700 transition hover:bg-blue-100 focus:outline-none focus:ring-4 focus:ring-blue-100"
                            @click="generateInventoryReport"
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
                                <path d="M6 9V2h12v7" />
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                <rect x="6" y="14" width="12" height="8" />
                            </svg>

                            <span>Generate Report</span>
                        </button>

                        <button
                            v-if="
                                canManageInventory
                                && !['reconciliation', 'returned'].includes(
                                    activeTab
                                )
                            "
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-blue-500 px-5 text-sm font-black text-white shadow-sm shadow-blue-100 transition hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                            @click="openAddItemModal"
                        >
                            <span class="text-lg leading-none">+</span>
                            <span>Add Item</span>
                        </button>

                        <div
                            v-if="
                                !canManageInventory
                                && activeTab !== 'reconciliation'
                            "
                            class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-xs font-black text-slate-500"
                        >
                            View Only
                        </div>
                    </div>
                </div>

                <!-- SEGMENTED TABS -->
                <div class="border-t border-slate-100 bg-slate-50/70 px-5 py-3 sm:px-6">
                    <div class="flex w-full flex-wrap rounded-xl border border-slate-200 bg-white p-1 sm:w-auto">
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
                            class="flex min-w-0 flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-black transition sm:min-w-[150px]"
                            :class="
                                activeTab === 'ict'
                                    ? 'bg-blue-500 text-white shadow-sm shadow-blue-100'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-blue-700'
                            "
                            @click="switchTab('ict')"
                        >
                            <span>ICT</span>
                        </button>

                        

                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-black transition sm:min-w-[180px]"
                            :class="
                                activeTab === 'other'
                                    ? 'bg-blue-500 text-white shadow-sm shadow-blue-100'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-blue-700'
                            "
                            @click="switchTab('other')"
                        >
                            <span>Other Items</span>
                        </button>

                        <button
                            type="button"
                            class="flex min-w-0 flex-1 items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-xs font-black transition sm:min-w-[165px]"
                            :class="
                                activeTab === 'returned'
                                    ? 'bg-rose-600 text-white shadow-sm shadow-rose-100'
                                    : 'text-slate-600 hover:bg-rose-50 hover:text-rose-700'
                            "
                            @click="switchTab('returned')"
                        >
                            <span>Returned</span>

                            <span
                                class="inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[9px] font-black"
                                :class="
                                    activeTab === 'returned'
                                        ? 'bg-white/20 text-white'
                                        : 'bg-rose-100 text-rose-700'
                                "
                            >
                                {{ returnedAssetCount }}
                            </span>
                        </button>

                    </div>

                    <div
                        v-if="activeTab === 'other'"
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <button
                            v-for="option in otherCategoryOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-lg border px-3 py-2 text-[10px] font-black transition"
                            :class="
                                otherCategoryFilter === option.value
                                    ? 'border-blue-500 bg-blue-50 text-blue-700 ring-2 ring-blue-100'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200 hover:bg-blue-50/50'
                            "
                            @click="switchOtherCategory(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
            </section>


            <!-- LEDGER WORKSPACE -->
            <section
                v-if="activeTab !== 'reconciliation'"
                class="inventory-ledger-surface mt-4 overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm"
            >
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
                                        activeTab === 'supplies'
                                        && quarterFilter !== 'all'
                                    "
                                    class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-black text-blue-700"
                                >
                                    {{ quarterFilter.toUpperCase() }}
                                </span>

                                <span
                                    v-if="
                                        ['ict', 'returned'].includes(
                                            activeTab
                                        )
                                        && mrFilter !== 'all'
                                    "
                                    class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[10px] font-black text-blue-700"
                                >
                                    {{ selectedMrName || 'Selected MR' }}
                                    ·
                                    {{ filteredMrAssetCount }}
                                    {{ filteredMrAssetCount === 1 ? 'asset' : 'assets' }}
                                </span>

                                <span
                                    v-if="activeTab === 'returned'"
                                    class="rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[10px] font-black text-rose-700"
                                >
                                    {{ filteredReturnedAssetRows.length }}
                                    returned
                                </span>
                            </div>
                           
                        </div>

                        <div
                            class="grid w-full grid-cols-1 gap-2 sm:grid-cols-2"
                            :class="
                                activeTab === 'other'
                                    ? 'xl:w-[520px] xl:grid-cols-1'
                                    : activeTab === 'supplies'
                                        ? 'xl:w-[1080px] xl:grid-cols-[minmax(260px,1fr)_110px_140px_120px_150px]'
                                        : activeTab === 'ict'
                                            ? 'xl:w-[1120px] xl:grid-cols-[minmax(240px,1fr)_110px_130px_220px_120px]'
                                            : activeTab === 'returned'
                                                ? 'xl:w-[980px] xl:grid-cols-[minmax(260px,1fr)_110px_130px_260px]'
                                                : 'xl:w-[520px] xl:grid-cols-1'
                            "
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
                                            : activeTab === 'ict'
                                                ? 'Search ICT...'
                                                : activeTab === 'returned'
                                                    ? 'Search returned ICT...'
                                                    : 'Search item, location, or remarks...'
                                    "
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-xs font-semibold text-slate-700 outline-none transition focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-100"
                                />
                            </div>

                            <select
                                v-if="activeTab !== 'other'"
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
                                v-if="activeTab !== 'other'"
                                v-model="unitFilter"
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            >
                                <option value="all">
                                    All units
                                </option>

                                <option
                                    v-for="unit in unitOptions"
                                    :key="unit.value"
                                    :value="unit.value"
                                >
                                    {{ unit.label }}
                                </option>
                            </select>

                            <select
                                v-if="['ict', 'returned'].includes(activeTab)"
                                v-model="mrFilter"
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                title="Filter ICT assets by MR"
                            >
                                <option value="all">
                                    All MR
                                </option>

                                <option
                                    v-for="person in mrPersonnelOptions"
                                    :key="`mr-filter-${person.id}`"
                                    :value="person.id"
                                >
                                    {{ person.name }}
                                </option>
                            </select>

                            <button
                                v-if="
                                    canManageInventory
                                    && ['supplies', 'ict'].includes(activeTab)
                                "
                                type="button"
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-blue-500 px-4 text-xs font-black text-white shadow-sm shadow-blue-100 transition hover:bg-blue-600 focus:outline-none focus:ring-4 focus:ring-blue-100"
                                @click="
                                    addUnitEditorOpen = true;
                                    addUnitError = '';
                                    addUnitValue = ''
                                "
                            >
                                <span class="text-base leading-none">
                                    +
                                </span>
                                <span>Add Unit</span>
                            </button>

                            <select
                                v-if="activeTab === 'supplies'"
                                v-model="quarterFilter"
                                class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
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
                    <table class="inventory-ledger-table w-full table-fixed">
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
                                    {{
                                        releaseIsIctAsset
                                            ? 'Property Number'
                                            : (
                                                releaseIsIct
                                                    ? `${releaseQuantityLabel} Released`
                                                    : 'Quantity Released'
                                            )
                                    }}
                                </th>

                                <th class="w-[16%] bg-blue-600 px-2 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    {{ releaseCurrentLabel }}
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


                                    <div
                                        v-if="visibleQuarterBalanceEntries(item).length"
                                        class="mt-2 flex flex-wrap gap-1"
                                    >
                                        <span
                                            v-for="entry in visibleQuarterBalanceEntries(item)"
                                            :key="`supply-balance-${item.id}-${entry.quarter}`"
                                            class="rounded-md border border-emerald-100 bg-emerald-50 px-2 py-1 text-[8px] font-black text-emerald-700"
                                        >
                                            {{ entry.quarter.toUpperCase() }}
                                            · Added: {{ entry.added }}
                                            · Remaining: {{ entry.current }}
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
                                    <div
                                        v-if="hasQuarterRemarks(item)"
                                        class="space-y-1.5"
                                    >
                                        <div
                                            v-for="entry in quarterRemarksEntries(item)"
                                            :key="`quarter-remarks-${item.id}-${entry.quarter}`"
                                            class="rounded-lg border border-blue-100 bg-blue-50/70 px-2.5 py-2"
                                        >
                                            <p class="text-[8px] font-black uppercase tracking-[0.08em] text-blue-500">
                                                {{ entry.quarter.toUpperCase() }} Remarks
                                            </p>
                                            <p class="mt-1 break-words text-[10px] font-semibold leading-4 text-blue-900">
                                                {{ entry.remarks }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        v-if="String(item.remarks || '').trim()"
                                        class="mt-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2"
                                    >
                                        <p class="text-[8px] font-black uppercase tracking-[0.08em] text-slate-400">
                                            General
                                        </p>
                                        <p class="mt-1 break-words text-[10px] font-semibold leading-4 text-slate-600">
                                            {{ item.remarks }}
                                        </p>
                                    </div>

                                    <span
                                        v-if="
                                            !hasQuarterRemarks(item)
                                            && !String(item.remarks || '').trim()
                                        "
                                        class="text-xs font-semibold text-slate-300"
                                    >
                                        —
                                    </span>
                                </td>

                                <!-- ACTION -->
                                <td class="px-2 py-3 text-center align-middle">
                                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                                        <button v-if="canManageInventory"
                                            type="button"
                                            class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[9px] font-black text-slate-700 transition hover:bg-slate-50"
                                            @click="openFullEditModal(item)"
                                        >
                                            Edit
                                        </button>

                                        <button v-if="canManageInventory"
                                            type="button"
                                            class="rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-[9px] font-black text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="
                                                currentAvailableValue(item) === null
                                                || Number(currentAvailableValue(item)) <= 0
                                                || !canReleaseInCurrentView(item)
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

                                        <button
                                            v-if="canManageInventory"
                                            type="button"
                                            title="Delete Item"
                                            aria-label="Delete Item"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100 focus:outline-none focus:ring-4 focus:ring-rose-100"
                                            @click="openDeleteItemModal(item)"
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
                                                <path d="M3 6h18" />
                                                <path d="M8 6V4h8v2" />
                                                <path d="M19 6l-1 14H6L5 6" />
                                                <path d="M10 11v5" />
                                                <path d="M14 11v5" />
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


                <!-- RETURNED ICT -->
                <div
                    v-if="activeTab === 'returned'"
                    class="overflow-hidden"
                >
                    <div
                        class="border-b border-rose-100 bg-rose-50/60 px-5 py-4 sm:px-6"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <h3
                                    class="text-sm font-black text-rose-900"
                                >
                                    Returned ICT Properties
                                </h3>

                                <p
                                    class="mt-1 text-[10px] font-semibold leading-5 text-rose-700/80"
                                >
                                    ICT units automatically appear here when their Status is changed to Returned.
                                </p>
                            </div>

                            <span
                                class="self-start rounded-full border border-rose-200 bg-white px-3 py-1.5 text-[10px] font-black text-rose-700 sm:self-auto"
                            >
                                {{ filteredReturnedAssetRows.length }}
                                {{
                                    filteredReturnedAssetRows.length === 1
                                        ? 'property'
                                        : 'properties'
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table
                            class="inventory-ledger-table w-full min-w-[1250px] table-fixed"
                        >
                            <thead class="bg-rose-600 text-white">
                                <tr>
                                    <th class="w-[15%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]">
                                        Item Name
                                    </th>

                                    <th class="w-[15%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]">
                                        Description
                                    </th>

                                    <th class="w-[13%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]">
                                        Accessories
                                    </th>

                                    <th class="w-[14%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]">
                                        Property Number
                                    </th>

                                    <th class="w-[13%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]">
                                        Current User
                                    </th>

                                    <th class="w-[10%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.09em]">
                                        Status
                                    </th>

                                    <th class="w-[12%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]">
                                        MR
                                    </th>

                                    <th class="w-[8%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.09em]">
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-rose-100">
                                <tr
                                    v-for="row in paginatedReturnedAssets"
                                    :key="`returned-${row.key}`"
                                    class="bg-white transition hover:bg-rose-50/45"
                                >
                                    <td class="px-3 py-3 align-top">
                                        <p
                                            class="break-words text-[11px] font-black leading-5 text-blue-950"
                                        >
                                            {{ row.item.item || '—' }}
                                        </p>

                                        <p
                                            class="mt-1 text-[9px] font-bold text-slate-400"
                                        >
                                            {{ row.item.inventory_year || '—' }}
                                            ·
                                            {{ row.item.unit || '—' }}
                                        </p>
                                    </td>

                                    <td class="px-3 py-3 align-top text-[11px] font-semibold leading-5 text-slate-700">
                                        {{ row.asset.description || '—' }}
                                    </td>

                                    <td class="px-3 py-3 align-top text-[11px] font-semibold leading-5 text-slate-600">
                                        {{ row.asset.accessories || '—' }}
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <span
                                            class="inline-flex rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[10px] font-black text-slate-800"
                                        >
                                            {{ row.asset.property_number || '—' }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 align-top text-[11px] font-semibold leading-5 text-slate-600">
                                        {{ row.asset.current_user || 'Unassigned' }}
                                    </td>

                                    <td class="px-3 py-3 text-center align-top">
                                        <span
                                            class="inline-flex rounded-full border border-rose-200 bg-rose-50 px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.06em] text-rose-700"
                                        >
                                            Returned
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 align-top text-[11px] font-semibold leading-5 text-slate-700">
                                        {{ row.asset.mr || '—' }}
                                    </td>

                                    <td class="px-3 py-3 text-center align-top">
                                        <div
                                            class="flex items-center justify-center"
                                        >

                                            <button
                                                type="button"
                                                title="View History"
                                                aria-label="View History"
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100"
                                                @click="
                                                    openHistoryModal(
                                                        row.item,
                                                        row.asset
                                                    )
                                                "
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

                                <tr
                                    v-if="!paginatedReturnedAssets.length"
                                >
                                    <td
                                        colspan="8"
                                        class="px-6 py-16 text-center"
                                    >
                                        <div
                                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-400"
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
                                                <path d="M3 12h18" />
                                                <path d="m8 7-5 5 5 5" />
                                            </svg>
                                        </div>

                                        <p
                                            class="mt-4 text-sm font-black text-slate-700"
                                        >
                                            No returned ICT properties found
                                        </p>

                                        <p
                                            class="mt-1 text-xs font-semibold text-slate-400"
                                        >
                                            Change an ICT Property Status to Returned and it will appear here automatically.
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>


                <!-- ICT TABLE -->
                <div
                    v-if="activeTab === 'ict'"
                    class="hidden overflow-hidden lg:block"
                >
                    <table class="inventory-ledger-table w-full table-fixed">
                        <thead class="border-b border-blue-700 bg-blue-600 text-white">
                            <tr>
                                <th class="w-[28%] px-4 py-3 text-left text-[9px] font-black uppercase tracking-[0.10em]">
                                    Item Name
                                </th>

                                <th class="w-[16%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Count / Duration
                                </th>

                                <th class="w-[12%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Available
                                </th>

                                <th class="w-[26%] px-4 py-3 text-left text-[9px] font-black uppercase tracking-[0.10em]">
                                    Remarks
                                </th>

                                <th class="w-[18%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <template
                                v-for="item in paginatedItems"
                                :key="`${activeTab}-${item.category}-${item.id || item.item}`"
                            >
                                <tr
                                    :role="hasVisibleIctAssetDetails(item) ? 'button' : undefined"
                                    :tabindex="hasVisibleIctAssetDetails(item) ? 0 : -1"
                                    :aria-expanded="hasVisibleIctAssetDetails(item) ? isIctExpanded(item) : undefined"
                                    :title="hasVisibleIctAssetDetails(item) ? 'Click to view Property Details' : undefined"
                                    class="bg-white transition"
                                    :class="
                                        hasVisibleIctAssetDetails(item)
                                            ? (
                                                isIctExpanded(item)
                                                    ? 'cursor-pointer bg-blue-50/70 hover:bg-blue-50'
                                                    : 'cursor-pointer hover:bg-blue-50/45'
                                            )
                                            : 'cursor-default hover:bg-blue-50/35'
                                    "
                                    @click="
                                        hasVisibleIctAssetDetails(item)
                                        && toggleIctAssetDetails(item)
                                    "
                                    @keydown.enter.prevent="
                                        hasVisibleIctAssetDetails(item)
                                        && toggleIctAssetDetails(item)
                                    "
                                    @keydown.space.prevent="
                                        hasVisibleIctAssetDetails(item)
                                        && toggleIctAssetDetails(item)
                                    "
                                >
                                    <td class="px-4 py-4 align-middle">
                                        <div class="flex items-center gap-2">
                                            <p class="min-w-0 flex-1 break-words text-xs font-black leading-5 text-blue-950">
                                                {{ item.item || '—' }}
                                            </p>

                                            <svg
                                                v-if="hasVisibleIctAssetDetails(item)"
                                                class="h-4 w-4 shrink-0 text-blue-600 transition-transform duration-200"
                                                :class="isIctExpanded(item) ? 'rotate-180' : ''"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2.25"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true"
                                            >
                                                <path d="m6 9 6 6 6-6" />
                                            </svg>
                                        </div>
                                    </td>

                                    <td class="px-3 py-4 text-center align-middle">
                                        <div class="flex flex-col items-center gap-1">
                                            <span
                                                class="inline-flex min-w-12 justify-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-black tabular-nums text-slate-800"
                                            >
                                                {{ ictQuantityDisplay(item) }}
                                            </span>

                                            <span class="text-[8px] font-black uppercase tracking-[0.08em] text-slate-400">
                                                {{ ictQuantityLabel(item) }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="px-3 py-4 text-center align-middle">
                                        <span
                                            v-if="!isIctSubscription(item)"
                                            class="inline-flex min-w-12 justify-center rounded-lg border px-2.5 py-1 text-[10px] font-black tabular-nums"
                                            :class="
                                                ictAvailableCount(item) > 0
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                    : 'border-rose-200 bg-rose-50 text-rose-700'
                                            "
                                        >
                                            {{ ictAvailableCount(item) }}
                                        </span>

                                        <span
                                            v-else
                                            class="text-xs font-black text-slate-300"
                                        >
                                            —
                                        </span>
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
                                        <div class="flex flex-wrap items-center justify-center gap-1.5">
                                            <button
                                                v-if="canManageInventory"
                                                type="button"
                                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black text-slate-700 transition hover:bg-slate-50"
                                                @click.stop="openFullEditModal(item)"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                v-if="canManageInventory"
                                                type="button"
                                                class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-[10px] font-black text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="
                                                    !canReleaseInventoryItem(item)
                                                    || !canReleaseInCurrentView(item)
                                                "
                                                @click.stop="openReleaseItemModal(item)"
                                            >
                                                Release
                                            </button>

                                            <button
                                                type="button"
                                                title="View History"
                                                aria-label="View History"
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700"
                                                @click.stop="openHistoryModal(item)"
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

                                            <button
                                                v-if="canManageInventory"
                                                type="button"
                                                title="Delete Item"
                                                aria-label="Delete Item"
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100"
                                                @click.stop="openDeleteItemModal(item)"
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
                                                    <path d="M3 6h18" />
                                                    <path d="M8 6V4h8v2" />
                                                    <path d="M19 6l-1 14H6L5 6" />
                                                    <path d="M10 11v5" />
                                                    <path d="M14 11v5" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr
                                    v-if="hasVisibleIctAssetDetails(item) && isIctExpanded(item)"
                                    class="bg-slate-50/80"
                                >
                                    <td
                                        colspan="5"
                                        class="px-4 pb-4 pt-1"
                                    >
                                        <div class="overflow-hidden rounded-xl border border-blue-100 bg-white shadow-sm">
                                            <div class="overflow-x-auto">
                                                <table class="w-full min-w-[1080px] table-fixed">
                                                    <thead
                                                        class="text-white"
                                                        style="background-color: #2563EB !important; color: #FFFFFF !important; border-bottom: 1px solid #1D4ED8 !important;"
                                                    >
                                                        <tr>
                                                            <th class="w-[20%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]" style="background-color: #2563EB !important; color: #FFFFFF !important;">
                                                                Description
                                                            </th>
                                                            <th class="w-[18%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]" style="background-color: #2563EB !important; color: #FFFFFF !important;">
                                                                Accessories
                                                            </th>
                                                            <th class="w-[16%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]" style="background-color: #2563EB !important; color: #FFFFFF !important;">
                                                                Property Number
                                                            </th>
                                                            <th class="w-[17%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]" style="background-color: #2563EB !important; color: #FFFFFF !important;">
                                                                Current User
                                                            </th>
                                                            <th class="w-[13%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.09em]" style="background-color: #2563EB !important; color: #FFFFFF !important;">
                                                                Status
                                                            </th>
                                                            <th class="w-[14%] px-3 py-3 text-left text-[9px] font-black uppercase tracking-[0.09em]" style="background-color: #2563EB !important; color: #FFFFFF !important;">
                                                                MR
                                                            </th>
                                                            <th class="w-[10%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.09em]" style="background-color: #2563EB !important; color: #FFFFFF !important;">
                                                                Action
                                                            </th>
                                                        </tr>
                                                    </thead>

                                                    <tbody class="divide-y divide-blue-100 bg-white">
                                                        <tr
                                                            v-for="(asset, assetIndex) in filteredIctAssetDetails(item)"
                                                            :key="`ict-asset-row-${item.id}-${assetIndex}`"
                                                            class="transition"
                                                            :class="ictLifeSpanRowClass(asset)"
                                                            :title="
                                                                ictLifeSpanState(asset)?.title
                                                                || undefined
                                                            "
                                                        >
                                                            <td class="px-3 py-3 align-top text-[11px] font-semibold leading-5 text-slate-700">
                                                                {{ asset.description || '—' }}
                                                            </td>

                                                            <td class="px-3 py-3 align-top text-[11px] font-semibold leading-5 text-slate-600">
                                                                {{ asset.accessories || '—' }}
                                                            </td>

                                                            <td class="px-3 py-3 align-top text-[11px] font-black leading-5 text-slate-800">
                                                                <div class="flex items-start gap-1.5">
                                                                    <span>
                                                                        {{ asset.property_number || '—' }}
                                                                    </span>

                                                                    <span
                                                                        v-if="ictAssetNeedsLifeSpanAttention(asset)"
                                                                        class="inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full border px-1 text-[10px] font-black"
                                                                        :class="ictLifeSpanBadgeClass(asset)"
                                                                        :title="ictLifeSpanState(asset)?.title"
                                                                        aria-label="Life span warning"
                                                                    >
                                                                        ⚠
                                                                    </span>
                                                                </div>
                                                            </td>

                                                            <td class="px-3 py-3 align-top text-[11px] font-semibold leading-5 text-slate-600">
                                                                {{ asset.current_user || 'Unassigned' }}
                                                            </td>

                                                            <td class="px-3 py-3 text-center align-top">
                                                                <div class="flex flex-col items-center gap-1.5">
                                                                    <span
                                                                        class="inline-flex rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.06em]"
                                                                        :class="ictAssetStatusClass(asset.status)"
                                                                    >
                                                                        {{ ictAssetStatusLabel(asset.status) }}
                                                                    </span>

                                                                    <span
                                                                        v-if="ictAssetNeedsLifeSpanAttention(asset)"
                                                                        class="inline-flex rounded-full border px-2 py-1 text-[8px] font-black uppercase tracking-[0.05em]"
                                                                        :class="ictLifeSpanBadgeClass(asset)"
                                                                        :title="ictLifeSpanState(asset)?.title"
                                                                    >
                                                                        ⚠
                                                                        {{ ictLifeSpanState(asset)?.label }}
                                                                    </span>
                                                                </div>
                                                            </td>

                                                            <td class="px-3 py-3 align-top text-[11px] font-semibold leading-5 text-slate-700">
                                                                {{ asset.mr || '—' }}
                                                            </td>

                                                            <td class="px-3 py-3 text-center align-top">
                                                                <button
                                                                    v-if="canManageInventory"
                                                                    type="button"
                                                                    class="rounded-lg border border-blue-200 bg-indigo-50 px-3 py-2 text-[10px] font-black text-blue-700 transition hover:bg-blue-100"
                                                                    @click.stop="
                                                                        openIctAssetEditModal(
                                                                            item,
                                                                            asset
                                                                        )
                                                                    "
                                                                >
                                                                    Edit
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!paginatedItems.length">
                                <td
                                    colspan="5"
                                    class="px-6 py-16 text-center"
                                >
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
                                        No ICT items found
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- OTHER ITEMS TABLE -->
                <div
                    v-if="activeTab === 'other'"
                    class="hidden overflow-hidden lg:block"
                >
                    <table class="inventory-ledger-table w-full table-fixed">
                        <thead class="bg-blue-500 text-white">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-[9px] font-black uppercase tracking-[0.10em]"
                                    :class="currentOtherCategoryIsAssetTracked ? 'w-[22%]' : 'w-[28%]'"
                                >
                                    Item
                                </th>

                                <th class="w-[10%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Count
                                </th>

                                <th
                                    v-if="currentOtherCategoryIsAssetTracked"
                                    class="w-[10%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]"
                                >
                                    Available
                                </th>

                                <th class="w-[20%] px-4 py-3 text-left text-[9px] font-black uppercase tracking-[0.10em]">
                                    Location
                                </th>

                                <th
                                    class="px-4 py-3 text-left text-[9px] font-black uppercase tracking-[0.10em]"
                                    :class="currentOtherCategoryIsAssetTracked ? 'w-[22%]' : 'w-[30%]'"
                                >
                                    Remarks
                                </th>

                                <th class="w-[16%] px-3 py-3 text-center text-[9px] font-black uppercase tracking-[0.10em]">
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <template
                                v-for="item in paginatedItems"
                                :key="`other-${item.category}-${item.id || item.item}`"
                            >
                                <tr
                                    :role="
                                        currentOtherCategoryIsAssetTracked
                                        && hasIctAssetDetails(item)
                                            ? 'button'
                                            : undefined
                                    "
                                    :tabindex="
                                        currentOtherCategoryIsAssetTracked
                                        && hasIctAssetDetails(item)
                                            ? 0
                                            : -1
                                    "
                                    :aria-expanded="
                                        currentOtherCategoryIsAssetTracked
                                        && hasIctAssetDetails(item)
                                            ? isIctExpanded(item)
                                            : undefined
                                    "
                                    :title="
                                        currentOtherCategoryIsAssetTracked
                                        && hasIctAssetDetails(item)
                                            ? 'Click to view Property Details'
                                            : undefined
                                    "
                                    class="bg-white transition"
                                    :class="
                                        currentOtherCategoryIsAssetTracked
                                        && hasIctAssetDetails(item)
                                            ? (
                                                isIctExpanded(item)
                                                    ? 'cursor-pointer bg-blue-50/70 hover:bg-blue-50'
                                                    : 'cursor-pointer hover:bg-blue-50/55'
                                            )
                                            : 'cursor-default hover:bg-blue-50/35'
                                    "
                                    @click="
                                        currentOtherCategoryIsAssetTracked
                                        && hasIctAssetDetails(item)
                                        && toggleIctAssetDetails(item)
                                    "
                                    @keydown.enter.prevent="
                                        currentOtherCategoryIsAssetTracked
                                        && hasIctAssetDetails(item)
                                        && toggleIctAssetDetails(item)
                                    "
                                    @keydown.space.prevent="
                                        currentOtherCategoryIsAssetTracked
                                        && hasIctAssetDetails(item)
                                        && toggleIctAssetDetails(item)
                                    "
                                >
                                    <td
                                        class="px-4 py-4 align-middle"
                                    >
                                        <div
                                            class="flex items-center gap-2"
                                        >
                                            <p
                                                class="min-w-0 flex-1 break-words text-xs font-black leading-5 text-slate-900"
                                            >
                                                {{ item.item }}
                                            </p>

                                            <svg
                                                v-if="currentOtherCategoryIsAssetTracked && hasIctAssetDetails(item)"
                                                class="h-4 w-4 shrink-0 text-blue-600 transition-transform duration-200"
                                                :class="isIctExpanded(item) ? 'rotate-180' : ''"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2.25"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                aria-hidden="true"
                                            >
                                                <path d="m6 9 6 6 6-6" />
                                            </svg>
                                        </div>
                                    </td>

                                    <td class="px-3 py-4 text-center align-middle">
                                        <span class="inline-flex min-w-10 justify-center rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-black tabular-nums text-slate-800">
                                            {{ item.currently_available ?? 0 }}
                                        </span>
                                    </td>

                                    <td
                                        v-if="currentOtherCategoryIsAssetTracked"
                                        class="px-3 py-4 text-center align-middle"
                                    >
                                        <span
                                            class="inline-flex min-w-10 justify-center rounded-lg border px-2.5 py-1.5 text-xs font-black tabular-nums"
                                            :class="
                                                ictAvailableCount(item) > 0
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                    : 'border-rose-200 bg-rose-50 text-rose-700'
                                            "
                                        >
                                            {{ ictAvailableCount(item) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-4 align-middle">
                                        <div class="inline-flex rounded-lg border border-blue-100 bg-blue-50 px-3 py-1.5 text-[11px] font-black text-blue-800">
                                            {{ item.location || '—' }}
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
                                        <div class="flex flex-wrap items-center justify-center gap-1.5">
                                            <button
                                                v-if="canManageInventory"
                                                type="button"
                                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-[10px] font-black text-slate-700 transition hover:bg-slate-50"
                                                @click.stop="openFullEditModal(item)"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                v-if="canManageInventory && currentOtherCategoryCanRelease"
                                                type="button"
                                                class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-[10px] font-black text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-40"
                                                :disabled="!canReleaseInventoryItem(item)"
                                                @click.stop="openReleaseItemModal(item)"
                                            >
                                                Release
                                            </button>

                                            <button
                                                type="button"
                                                title="View History"
                                                aria-label="View History"
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700"
                                                @click.stop="openHistoryModal(item)"
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

                                            <button
                                                v-if="canManageInventory"
                                                type="button"
                                                title="Delete Item"
                                                aria-label="Delete Item"
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 transition hover:bg-rose-100"
                                                @click.stop="openDeleteItemModal(item)"
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
                                                    <path d="M3 6h18" />
                                                    <path d="M8 6V4h8v2" />
                                                    <path d="M19 6l-1 14H6L5 6" />
                                                    <path d="M10 11v5" />
                                                    <path d="M14 11v5" />
                                                </svg>
                                            </button>

                                        </div>
                                    </td>
                                </tr>

                                <tr
                                    v-if="currentOtherCategoryIsAssetTracked && hasIctAssetDetails(item) && isIctExpanded(item)"
                                    class="bg-slate-50/80"
                                >
                                    <td
                                        colspan="6"
                                        class="px-4 pb-4 pt-1"
                                    >
                                        <div class="overflow-hidden rounded-xl border border-blue-100 bg-white">
                                            <div class="grid grid-cols-[1fr_1fr_1fr_auto] items-center gap-4 border-b border-blue-700 bg-blue-600 px-4 py-2.5 text-[9px] font-black uppercase tracking-[0.09em] text-white">
                                                <span>Description</span>
                                                <span>Property Number</span>
                                                <span>Current User</span>
                                                <span class="text-center">Action</span>
                                            </div>

                                            <div
                                                v-for="(asset, assetIndex) in ictAssetDetails(item)"
                                                :key="`other-asset-row-${item.id}-${assetIndex}`"
                                                class="grid grid-cols-[1fr_1fr_1fr_auto] items-center gap-4 border-b border-blue-100 px-4 py-3 last:border-b-0"
                                            >
                                                <span class="break-words text-[11px] font-semibold text-slate-700">
                                                    {{ asset.description || '—' }}
                                                </span>

                                                <span class="break-words text-[11px] font-black text-slate-800">
                                                    {{ asset.property_number || '—' }}
                                                </span>

                                                <span class="break-words text-[11px] font-semibold text-slate-600">
                                                    {{ asset.current_user || 'Unassigned' }}
                                                </span>

                                                <button
                                                    v-if="canManageInventory"
                                                    type="button"
                                                    class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-[10px] font-black text-blue-700 transition hover:bg-blue-100"
                                                    @click.stop="
                                                        openIctAssetEditModal(
                                                            item,
                                                            asset
                                                        )
                                                    "
                                                >
                                                    Edit
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!paginatedItems.length">
                                <td
                                    :colspan="currentOtherCategoryIsAssetTracked ? 6 : 5"
                                    class="px-6 py-16 text-center"
                                >
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
                                        No {{ currentOtherCategoryLabel.toLowerCase() }} found
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- OTHER ITEMS MOBILE -->
                <div
                    v-if="activeTab === 'other'"
                    class="space-y-3 p-4 lg:hidden"
                >
                    <article
                        v-for="item in paginatedItems"
                        :key="`mobile-other-${item.category}-${item.id || item.item}`"
                        :role="
                            currentOtherCategoryIsAssetTracked
                            && hasIctAssetDetails(item)
                                ? 'button'
                                : undefined
                        "
                        :tabindex="
                            currentOtherCategoryIsAssetTracked
                            && hasIctAssetDetails(item)
                                ? 0
                                : -1
                        "
                        :aria-expanded="
                            currentOtherCategoryIsAssetTracked
                            && hasIctAssetDetails(item)
                                ? isIctExpanded(item)
                                : undefined
                        "
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition"
                        :class="
                            currentOtherCategoryIsAssetTracked
                            && hasIctAssetDetails(item)
                                ? (
                                    isIctExpanded(item)
                                        ? 'cursor-pointer border-blue-200 bg-blue-50/40'
                                        : 'cursor-pointer hover:border-blue-200 hover:bg-blue-50/35'
                                )
                                : 'cursor-default'
                        "
                        @click="
                            currentOtherCategoryIsAssetTracked
                            && hasIctAssetDetails(item)
                            && toggleIctAssetDetails(item)
                        "
                        @keydown.enter.prevent="
                            currentOtherCategoryIsAssetTracked
                            && hasIctAssetDetails(item)
                            && toggleIctAssetDetails(item)
                        "
                        @keydown.space.prevent="
                            currentOtherCategoryIsAssetTracked
                            && hasIctAssetDetails(item)
                            && toggleIctAssetDetails(item)
                        "
                    >
                        <div
                            class="border-b border-slate-100 px-4 py-4"
                        >
                            <div
                                class="flex items-center gap-2"
                            >
                                <p
                                    class="min-w-0 flex-1 break-words text-sm font-black leading-5 text-slate-900"
                                >
                                    {{ item.item }}
                                </p>

                                <svg
                                    v-if="currentOtherCategoryIsAssetTracked && hasIctAssetDetails(item)"
                                    class="h-4 w-4 shrink-0 text-blue-600 transition-transform duration-200"
                                    :class="isIctExpanded(item) ? 'rotate-180' : ''"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.25"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="m6 9 6 6 6-6" />
                                </svg>
                            </div>
                        </div>

                        <div class="space-y-3 p-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-[9px] font-black uppercase tracking-[0.13em] text-slate-400">
                                    Count
                                </p>

                                <p class="mt-1 text-sm font-black tabular-nums text-slate-900">
                                    {{ item.currently_available ?? 0 }}
                                </p>
                            </div>

                            <div
                                v-if="currentOtherCategoryIsAssetTracked"
                                class="rounded-xl border border-emerald-100 bg-emerald-50 p-3"
                            >
                                <p class="text-[9px] font-black uppercase tracking-[0.13em] text-emerald-600">
                                    Available
                                </p>

                                <p class="mt-1 text-sm font-black tabular-nums text-emerald-900">
                                    {{ ictAvailableCount(item) }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-blue-100 bg-blue-50 p-3">
                                <p class="text-[9px] font-black uppercase tracking-[0.13em] text-blue-500">
                                    Location
                                </p>

                                <p class="mt-1 break-words text-xs font-black leading-5 text-blue-900">
                                    {{ item.location || '—' }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[9px] font-black uppercase tracking-[0.13em] text-slate-400">
                                    Remarks
                                </p>

                                <p class="mt-1 break-words text-xs font-semibold leading-5 text-slate-600">
                                    {{ String(item.remarks || '').trim() || '—' }}
                                </p>
                            </div>

                            <div
                                v-if="currentOtherCategoryIsAssetTracked && hasIctAssetDetails(item) && isIctExpanded(item)"
                                class="overflow-hidden rounded-xl border border-blue-100 bg-white"
                                @click.stop
                                @keydown.stop
                            >
                                <div class="grid grid-cols-[1fr_1fr_1fr_auto] items-center gap-2 border-b border-blue-700 bg-blue-600 px-3 py-2.5 text-[8px] font-black uppercase tracking-[0.09em] text-white">
                                    <span>Description</span>
                                    <span>Property Number</span>
                                    <span>Current User</span>
                                    <span class="text-center">Action</span>
                                </div>

                                <div
                                    v-for="(asset, assetIndex) in ictAssetDetails(item)"
                                    :key="`mobile-other-asset-${item.id}-${assetIndex}`"
                                    class="grid grid-cols-[1fr_1fr_1fr_auto] items-center gap-2 border-b border-blue-100 px-3 py-2.5 last:border-b-0"
                                >
                                    <span class="break-words text-[10px] font-semibold text-slate-700">
                                        {{ asset.description || '—' }}
                                    </span>
                                    <span class="break-words text-[10px] font-black text-slate-800">
                                        {{ asset.property_number || '—' }}
                                    </span>
                                    <span class="break-words text-[10px] font-semibold text-slate-600">
                                        {{ asset.current_user || 'Unassigned' }}
                                    </span>

                                    <button
                                        v-if="canManageInventory"
                                        type="button"
                                        class="rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-2 text-[9px] font-black text-blue-700 transition hover:bg-blue-100"
                                        @click.stop="
                                            openIctAssetEditModal(
                                                item,
                                                asset
                                            )
                                        "
                                    >
                                        Edit
                                    </button>
                                </div>
                            </div>

                            <div class="flex flex-wrap justify-end gap-2">
                                <button
                                    v-if="canManageInventory"
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-[10px] font-black text-slate-700 transition hover:bg-slate-50"
                                    @click.stop="openFullEditModal(item)"
                                >
                                    Edit
                                </button>

                                <button
                                    v-if="canManageInventory && currentOtherCategoryCanRelease"
                                    type="button"
                                    class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-[10px] font-black text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-40"
                                    :disabled="!canReleaseInventoryItem(item)"
                                    @click.stop="openReleaseItemModal(item)"
                                >
                                    Release
                                </button>

                                <button
                                    type="button"
                                    title="View History"
                                    aria-label="View History"
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700"
                                    @click.stop="openHistoryModal(item)"
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

                                <button
                                    v-if="canManageInventory"
                                    type="button"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-[10px] font-black text-rose-700 transition hover:bg-rose-100"
                                    @click.stop="openDeleteItemModal(item)"
                                >
                                    Delete
                                </button>

                            </div>
                        </div>
                    </article>

                    <div
                        v-if="!paginatedItems.length"
                        class="rounded-2xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center"
                    >
                        <p class="text-sm font-black text-slate-600">
                            No {{ currentOtherCategoryLabel.toLowerCase() }} found
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
                            {{ activeFilteredCount }}
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


        <!-- ADD UNIT MODAL -->
        <div
            v-if="addUnitEditorOpen"
            class="fixed inset-0 z-[95] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
            @click.self="
                addUnitEditorOpen = false;
                addUnitValue = '';
                addUnitError = ''
            "
        >
            <div
                class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                >
                    <div>
                        <p
                            class="text-[10px] font-black uppercase tracking-[0.14em] text-blue-600"
                        >
                            Inventory Unit
                        </p>

                        <h3
                            class="mt-1 text-lg font-black text-slate-900"
                        >
                            Add Unit ·
                            {{
                                activeTab === 'supplies'
                                    ? 'Supplies'
                                    : 'ICT'
                            }}
                        </h3>
                    </div>

                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg font-black text-slate-500 transition hover:bg-slate-50"
                        @click="
                            addUnitEditorOpen = false;
                            addUnitValue = '';
                            addUnitError = ''
                        "
                    >
                        ×
                    </button>
                </div>

                <div class="p-5">
                    <label
                        class="mb-2 block text-sm font-black text-slate-800"
                    >
                        Unit Name
                    </label>

                    <input
                        v-model="addUnitValue"
                        type="text"
                        maxlength="50"
                        autofocus
                        placeholder="Example: BOTTLE, LICENSE, SET"
                        class="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold uppercase text-slate-800 outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        @keyup.enter.prevent="
                            saveToolbarCustomUnit
                        "
                    />

                    <p
                        v-if="addUnitError"
                        class="mt-2 text-xs font-bold text-rose-600"
                    >
                        {{ addUnitError }}
                    </p>

                    <p
                        class="mt-3 text-xs font-semibold leading-5 text-slate-500"
                    >
                        The new unit will automatically appear in the
                        Unit dropdown for
                        {{
                            activeTab === 'supplies'
                                ? 'Supplies'
                                : 'ICT'
                        }}.
                    </p>
                </div>

                <div
                    class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4"
                >
                    <button
                        type="button"
                        class="h-10 rounded-xl border border-slate-200 bg-white px-4 text-xs font-black text-slate-600 transition hover:bg-slate-100"
                        @click="
                            addUnitEditorOpen = false;
                            addUnitValue = '';
                            addUnitError = ''
                        "
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="h-10 rounded-xl bg-blue-600 px-5 text-xs font-black text-white transition hover:bg-blue-700"
                        @click="
                            saveToolbarCustomUnit
                        "
                    >
                        Save Unit
                    </button>
                </div>
            </div>
        </div>


        <!-- ADD ITEM MODAL -->
        <div
            v-if="canManageInventory && showAddItemModal"
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
                                        : activeTab === 'ict'
                                            ? 'ICT'
                                            : `Other Items · ${currentOtherCategoryLabel}`
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
                            ICT
                        </p>
                        <p class="mt-1 text-[11px] font-semibold leading-5 text-blue-700/80">
                            Normal ICT items use a Count. For subscriptions, select <strong>Month (Subscription)</strong> or <strong>Year (Subscription)</strong>, then enter the subscription duration.
                        </p>
                    </div>

                    <div
                        v-if="activeTab === 'other'"
                        class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3"
                    >
                        <p class="text-xs font-black text-blue-900">
                            Add Other Item
                        </p>
                        <p class="mt-1 text-[11px] font-semibold leading-5 text-blue-700">
                            Select Furniture/Fixtures, Emergency Kits, or Token and Giveaways, then complete the details below.
                        </p>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-5 md:grid-cols-2"
                    >
                        <!-- OTHER ITEMS CATEGORY -->
                        <div
                            v-if="activeTab === 'other'"
                        >
                            <label
                                class="mb-2 block text-sm font-black text-slate-800"
                            >
                                Category
                            </label>

                            <select
                                v-model="newItemForm.other_category"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            >
                                <option
                                    v-for="option in otherCategoryOptions"
                                    :key="`add-other-${option.value}`"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>


                        <!-- ITEM NAME -->
                        <div
                            :class="
                                activeTab === 'other'
                                    ? ''
                                    : 'md:col-span-2'
                            "
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


                        <!-- COUNT — OTHER ITEMS -->
                        <div
                            v-if="
                                activeTab === 'other'
                                && addOtherCategoryHasCount
                            "
                        >
                            <label
                                class="mb-2 block text-sm font-black text-slate-800"
                            >
                                Count
                            </label>

                            <input
                                v-model.number="newItemForm.currently_available"
                                type="number"
                                min="0"
                                step="1"
                                placeholder="Enter number of items"
                                class="h-11 w-full rounded-xl border bg-white px-4 text-sm font-black tabular-nums text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
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
                                {{ addItemErrors.currently_available }}
                            </p>
                        </div>


                        <!-- LOCATION — OTHER ITEMS ONLY -->
                        <div
                            v-if="activeTab === 'other'"
                        >
                            <label
                                class="mb-2 block text-sm font-black text-slate-800"
                            >
                                Location
                            </label>

                            <input
                                v-model="newItemForm.location"
                                type="text"
                                placeholder="Example: SPD Library"
                                class="h-11 w-full rounded-xl border bg-white px-4 text-sm font-semibold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                :class="
                                    addItemErrors.location
                                        ? 'border-rose-400'
                                        : 'border-slate-200 focus:border-blue-400'
                                "
                            />

                            <p
                                v-if="addItemErrors.location"
                                class="mt-2 text-xs font-bold text-rose-600"
                            >
                                {{ addItemErrors.location }}
                            </p>
                        </div>


                        <!-- UNIT OF MEASURE -->
                        <div v-if="activeTab !== 'other'">
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



                        <!-- ICT INVENTORY YEAR -->
                        <div v-if="activeTab === 'ict'">
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
                                    :key="`add-ict-year-${year}`"
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


                        <!-- ICT COUNT / SUBSCRIPTION DURATION -->
                        <div
                            v-if="activeTab === 'ict'"
                            class="md:col-span-2"
                        >
                            <label class="mb-2 block text-sm font-black text-slate-800">
                                {{
                                    isIctMonthBased(newItemForm.unit)
                                        ? 'Subscription Duration (Month/s)'
                                        : isIctYearBased(newItemForm.unit)
                                            ? 'Subscription Duration (Year/s)'
                                            : 'Count'
                                }}
                            </label>

                            <input
                                v-model.number="newItemForm.currently_available"
                                type="number"
                                min="0"
                                step="1"
                                :placeholder="
                                    isIctMonthBased(newItemForm.unit)
                                        ? 'Example: 12 months'
                                        : isIctYearBased(newItemForm.unit)
                                            ? 'Example: 1 year'
                                            : 'Enter count'
                                "
                                class="h-11 w-full rounded-xl border bg-white px-4 text-sm font-black tabular-nums text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
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
                                {{ addItemErrors.currently_available }}
                            </p>
                        </div>


                        <!-- ICT PROPERTY DETAILS -->
                        <div
                            v-if="
                                (
                                    activeTab === 'ict'
                                    && !isIctSubscription(newItemForm.unit)
                                )
                                || (
                                    activeTab === 'other'
                                    && addOtherCategoryIsAssetTracked
                                )
                            "
                            class="sm:col-span-2 rounded-2xl border border-blue-100 bg-blue-50/40 p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <div>
                                    <p class="text-sm font-black text-slate-900">
                                        Property Details
                                    </p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">
                                        {{
                                            activeTab === 'ict'
                                                ? 'Each ICT unit tracks Description, Accessories, Property Number, Current User, Life Span Ended, Status, and MR.'
                                                : (
                                                    activeTab === 'other'
                                                    && addOtherCategoryIsAssetTracked
                                                        ? 'Each Furniture/Fixtures unit requires Description and Property Number. Current User is optional.'
                                                        : 'The number of property rows automatically follows the Count. Property Number is required; Current User is optional.'
                                                )
                                        }}
                                    </p>
                                </div>

                                <span
                                    class="rounded-xl border border-blue-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-[0.08em] text-blue-700"
                                >
                                    {{
                                        ictEquipmentCount(
                                            newItemForm.currently_available
                                        )
                                    }}
                                    Property
                                    {{
                                        ictEquipmentCount(
                                            newItemForm.currently_available
                                        ) === 1
                                            ? 'Row'
                                            : 'Rows'
                                    }}
                                </span>
                            </div>

                            <p
                                v-if="addItemErrors.ict_assets"
                                class="mt-3 text-xs font-bold text-rose-600"
                            >
                                {{ addItemErrors.ict_assets }}
                            </p>

                            <div
                                v-if="newItemForm.ict_assets.length"
                                class="mt-4 space-y-3"
                            >
                                <div
                                    v-for="(asset, assetIndex) in newItemForm.ict_assets"
                                    :key="`new-ict-asset-${assetIndex}`"
                                    class="grid gap-3 rounded-xl border border-slate-200 bg-white p-3"
                                    :class="
                                        activeTab === 'ict'
                                            ? 'sm:grid-cols-2'
                                            : (
                                                activeTab === 'other'
                                                && addOtherCategoryIsAssetTracked
                                            )
                                                ? 'sm:grid-cols-3'
                                                : 'sm:grid-cols-2'
                                    "
                                >
                                    <div
                                        v-if="
                                            activeTab === 'ict'
                                            || (
                                                activeTab === 'other'
                                                && addOtherCategoryIsAssetTracked
                                            )
                                        "
                                    >
                                        <label
                                            class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-blue-700"
                                        >
                                            Description
                                            <span class="text-rose-500">*</span>
                                        </label>

                                        <input
                                            v-model="asset.description"
                                            type="text"
                                            maxlength="255"
                                            :placeholder="`Description #${assetIndex + 1}`"
                                            class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-semibold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                            :class="
                                                addItemErrors[`ict_assets.${assetIndex}.description`]
                                                    ? 'border-rose-400'
                                                    : 'border-blue-200 focus:border-blue-400'
                                            "
                                        />

                                        <p
                                            v-if="addItemErrors[`ict_assets.${assetIndex}.description`]"
                                            class="mt-1 text-[10px] font-bold text-rose-600"
                                        >
                                            {{ addItemErrors[`ict_assets.${assetIndex}.description`] }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-blue-700"
                                        >
                                            Property Number
                                            <span class="text-rose-500">*</span>
                                        </label>

                                        <input
                                            v-model="asset.property_number"
                                            type="text"
                                            maxlength="100"
                                            :placeholder="`Property #${assetIndex + 1}`"
                                            class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-bold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                            :class="
                                                addItemErrors[`ict_assets.${assetIndex}.property_number`]
                                                    ? 'border-rose-400'
                                                    : 'border-blue-200 focus:border-blue-400'
                                            "
                                        />

                                        <p
                                            v-if="addItemErrors[`ict_assets.${assetIndex}.property_number`]"
                                            class="mt-1 text-[10px] font-bold text-rose-600"
                                        >
                                            {{ addItemErrors[`ict_assets.${assetIndex}.property_number`] }}
                                        </p>
                                    </div>

                                                                        <div
                                        v-if="activeTab === 'ict'"
                                        class="sm:col-span-2 -mb-1 mt-1"
                                    >
                                        <p class="text-[9px] font-black uppercase tracking-[0.10em] text-emerald-700">
                                            Assignment & Status
                                        </p>
                                    </div>

<div>
                                        <label
                                            class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-emerald-700"
                                        >
                                            Current User
                                            <span class="font-semibold normal-case tracking-normal text-slate-400">
                                                (Optional)
                                            </span>
                                        </label>

                                        <input
                                            v-model="asset.current_user"
                                            type="text"
                                            maxlength="255"
                                            placeholder="Employee / Office / User"
                                            class="h-10 w-full rounded-lg border border-emerald-200 bg-emerald-50/40 px-3 text-xs font-semibold text-emerald-950 outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                                        />
                                    </div>

<div v-if="activeTab === 'ict'">
                                        <label
                                            class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-emerald-700"
                                        >
                                            Status
                                            <span class="text-rose-500">*</span>
                                        </label>

                                        <select
                                            v-model="asset.status"
                                            class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-bold text-emerald-950 outline-none focus:ring-4 focus:ring-emerald-100"
                                            :class="
                                                addItemErrors[`ict_assets.${assetIndex}.status`]
                                                    ? 'border-rose-400'
                                                    : 'border-emerald-200 bg-emerald-50/40 focus:border-emerald-400'
                                            "
                                        >
                                            <option
                                                v-for="option in ictAssetStatusOptions"
                                                :key="`add-status-${assetIndex}-${option.value}`"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>

                                        <p
                                            v-if="addItemErrors[`ict_assets.${assetIndex}.status`]"
                                            class="mt-1 text-[10px] font-bold text-rose-600"
                                        >
                                            {{ addItemErrors[`ict_assets.${assetIndex}.status`] }}
                                        </p>
                                    </div>

                                    <div
                                        v-if="activeTab === 'ict'"
                                        class="rounded-xl border border-rose-200 bg-rose-50/60 p-3 sm:col-span-2"
                                    >
                                        <p class="mb-3 text-[9px] font-black uppercase tracking-[0.10em] text-rose-700">
                                            Asset Life Cycle
                                        </p>

                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-rose-700">
                                                    Date Acquired
                                                </label>

                                                <input
                                                    v-model="asset.date_acquired"
                                                    type="date"
                                                    class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-bold text-rose-950 outline-none focus:ring-4 focus:ring-rose-100"
                                                    :class="
                                                        addItemErrors[`ict_assets.${assetIndex}.date_acquired`]
                                                            ? 'border-rose-400'
                                                            : 'border-rose-200 bg-white focus:border-rose-400'
                                                    "
                                                />

                                                <p
                                                    v-if="addItemErrors[`ict_assets.${assetIndex}.date_acquired`]"
                                                    class="mt-1 text-[10px] font-bold text-rose-600"
                                                >
                                                    {{ addItemErrors[`ict_assets.${assetIndex}.date_acquired`] }}
                                                </p>
                                            </div>

                                            <div>
                                                <label class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-rose-700">
                                                    Life Span Ended
                                                </label>

                                                <input
                                                    v-model="asset.life_span_ended"
                                                    type="date"
                                                    class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-bold text-rose-950 outline-none focus:ring-4 focus:ring-rose-100"
                                                    :class="
                                                        addItemErrors[`ict_assets.${assetIndex}.life_span_ended`]
                                                            ? 'border-rose-400'
                                                            : 'border-rose-200 bg-white focus:border-rose-400'
                                                    "
                                                />

                                                <p
                                                    v-if="addItemErrors[`ict_assets.${assetIndex}.life_span_ended`]"
                                                    class="mt-1 text-[10px] font-bold text-rose-600"
                                                >
                                                    {{ addItemErrors[`ict_assets.${assetIndex}.life_span_ended`] }}
                                                </p>
                                            </div>
                                        </div>

                                        <p class="mt-2 text-[9px] font-semibold leading-4 text-slate-400">
                                            Red warning starts automatically when 365 days or less remain before the Life Span Ended date.
                                        </p>
                                    </div>

                                    <div v-if="activeTab === 'ict'">
                                        <label
                                            class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500"
                                        >
                                            Accessories
                                            <span class="font-semibold normal-case tracking-normal text-slate-400">
                                                (Optional)
                                            </span>
                                        </label>

                                        <input
                                            v-model="asset.accessories"
                                            type="text"
                                            maxlength="500"
                                            placeholder="Mouse, charger, bag, etc."
                                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                        />
                                    </div>

                                    

                                    <div v-if="activeTab === 'ict'">
                                        <label
                                            class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-indigo-700"
                                        >
                                            MR
                                            <span class="font-semibold normal-case tracking-normal text-slate-400">
                                                (Permanent Employee)
                                            </span>
                                        </label>

                                        <select
                                            v-model="asset.mr_personnel_id"
                                            class="h-10 w-full rounded-lg border border-indigo-200 bg-indigo-50/40 px-3 text-xs font-semibold text-indigo-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                                            @change="syncAssetMr(asset)"
                                        >
                                            <option value="">
                                                No MR / Unassigned
                                            </option>

                                            <option
                                                v-for="person in mrPersonnelOptions"
                                                :key="`add-mr-${assetIndex}-${person.id}`"
                                                :value="person.id"
                                            >
                                                {{ person.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-4 rounded-xl border border-dashed border-blue-200 bg-white/70 px-4 py-5 text-center text-xs font-semibold text-slate-400"
                            >
                                Enter a Count above to generate the Property Detail rows.
                            </div>
                        </div>


                        <!-- INVENTORY YEAR - SUPPLIES -->
                        <div v-if="activeTab === 'supplies'">
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
                                Optional reference value. Actual remaining and released quantities are now tracked separately per quarter.
                            </p>
                        </div>



                    </div>


                    <!-- QUARTERS -->
                    <div v-if="activeTab === 'supplies'">
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
                                    type="checkbox"
                                    :value="quarter.value"
                                    :checked="
                                        newItemForm.quarters
                                            .includes(
                                                quarter.value
                                            )
                                    "
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    @change="
                                        toggleNewItemQuarter(
                                            quarter.value,
                                            $event.target.checked
                                        )
                                    "
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

                        <div
                            v-if="
                                activeTab === 'supplies'
                                && newItemForm.quarters.length
                            "
                            class="mt-4 rounded-2xl border border-blue-100 bg-blue-50/40 p-4"
                        >
                            <div>
                                <p class="text-xs font-black text-slate-800">
                                    New Quantity per Quarter
                                </p>
                                <p class="mt-1 text-[10px] font-semibold leading-4 text-slate-500">
                                    Remaining stock automatically carries forward. Example: if Q1 has 10 remaining and you add 25 in Q2, Q2 starts with 35 while Q1 still shows 10 as its historical remaining balance.
                                </p>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div
                                    v-for="quarter in newItemForm.quarters"
                                    :key="`new-quarter-stock-${quarter}`"
                                    class="rounded-xl border border-slate-200 bg-white p-3"
                                >
                                    <label class="mb-2 block text-xs font-black text-slate-800">
                                        {{ quarter.toUpperCase() }}
                                        ·
                                        {{
                                            'New Quantity'
                                        }}
                                    </label>

                                    <input
                                        :value="
                                            quarterStockFormCurrent(
                                                newItemForm,
                                                quarter
                                            )
                                        "
                                        type="number"
                                        min="0"
                                        step="1"
                                        placeholder="Enter new quantity for this quarter"
                                        class="h-11 w-full rounded-xl border bg-white px-3 text-sm font-black tabular-nums text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                        :class="
                                            addItemErrors[
                                                `quarter_stock.${quarter}.current`
                                            ]
                                                ? 'border-rose-400'
                                                : 'border-slate-200 focus:border-blue-400'
                                        "
                                        @input="
                                            setNewQuarterStockCurrent(
                                                quarter,
                                                $event.target.value
                                            )
                                        "
                                    />

                                    <p
                                        v-if="
                                            addItemErrors[
                                                `quarter_stock.${quarter}.current`
                                            ]
                                        "
                                        class="mt-2 text-[10px] font-bold text-rose-600"
                                    >
                                        {{
                                            addItemErrors[
                                                `quarter_stock.${quarter}.current`
                                            ]
                                        }}
                                    </p>


                                    <div class="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-[9px] font-semibold leading-4 text-slate-500">
                                        <p>
                                            Carryover:
                                            <strong class="text-slate-700">
                                                {{
                                                    projectedQuarterCarryover(
                                                        newItemForm,
                                                        quarter
                                                    )
                                                }}
                                            </strong>
                                        </p>

                                        <p>
                                            Resulting balance:
                                            <strong class="text-emerald-700">
                                                {{
                                                    projectedFormQuarterCurrent(
                                                        newItemForm,
                                                        quarter
                                                    )
                                                }}
                                            </strong>
                                        </p>
                                    </div>

                                    <div class="mt-3 border-t border-slate-100 pt-3">
                                        <label class="mb-2 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500">
                                            {{ quarter.toUpperCase() }} Remarks
                                            <span class="font-semibold normal-case tracking-normal text-slate-400">
                                                (Optional)
                                            </span>
                                        </label>

                                        <textarea
                                            :value="quarterStockFormRemarks(newItemForm, quarter)"
                                            rows="2"
                                            maxlength="1000"
                                            :placeholder="`Remarks for ${quarter.toUpperCase()}...`"
                                            class="w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold leading-5 text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                            @input="
                                                setNewQuarterStockRemarks(
                                                    quarter,
                                                    $event.target.value
                                                )
                                            "
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>


                                        <!-- ICT MR HOLDER -->
                    <div v-if="releaseIsIctAsset">
                        <label class="mb-2 block text-sm font-black text-indigo-950">
                            MR Holder
                            <span class="text-rose-500">*</span>
                        </label>

                        <select
                            v-model="releaseItemForm.releaseMrPersonnelId"
                            class="h-12 w-full rounded-xl border bg-white px-4 text-sm font-semibold text-indigo-950 outline-none transition focus:ring-4 focus:ring-indigo-100"
                            :class="
                                releaseItemErrors.releaseMrPersonnelId
                                    ? 'border-rose-400'
                                    : 'border-indigo-200 bg-indigo-50/40 focus:border-indigo-400'
                            "
                            @change="syncReleaseMrHolder"
                        >
                            <option value="" disabled>
                                Select MR holder
                            </option>

                            <option
                                v-for="person in mrPersonnelOptions"
                                :key="`release-mr-${person.id}`"
                                :value="person.id"
                            >
                                {{ person.name }}
                            </option>
                        </select>

                        <p
                            v-if="releaseItemErrors.releaseMrPersonnelId"
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{ releaseItemErrors.releaseMrPersonnelId }}
                        </p>

                        <p class="mt-2 text-[10px] font-semibold leading-4 text-slate-400">
                            Saved directly to the selected ICT Property Number.
                        </p>
                    </div>

                    <!-- REMARKS -->
                    <div>
                        <label
                            class="mb-2 block text-sm font-black text-slate-800"
                        >
                            {{
                                activeTab === 'supplies'
                                    ? 'General Remarks'
                                    : 'Remarks'
                            }}
                        </label>

                        <textarea
                            v-model="
                                newItemForm.remarks
                            "
                            rows="3"
                            :placeholder="
                                activeTab === 'supplies'
                                    ? 'Optional general remarks for the whole item...'
                                    : 'Optional remarks...'
                            "
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
            v-if="canManageInventory && showFullEditModal && fullEditingItem"
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
                                {{
                                    fullEditingItem.item
                                    || 'Inventory Item'
                                }}
                            </h3>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                {{
                                    fullEditingItem.category === 'ict'
                                    && !isIctSubscription(fullEditingItem)
                                        ? 'Edit the item details or add new units. Edit each Property Detail from the accordion.'
                                        : 'Edit item details here. Quantity Released remains automatic.'
                                }}
                            </p>
                        </div>

                        <div
                            class="flex shrink-0 items-center gap-2"
                        >
                            <button
                                type="button"
                                title="View History"
                                aria-label="View History"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-slate-200"
                                @click="
                                    openHistoryModal(
                                        fullEditingItem
                                    )
                                "
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

                            <button
                                type="button"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-lg font-bold text-slate-500 transition hover:bg-slate-50"
                                @click="closeFullEditModal"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                </div>

                <form
                    class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6"
                    @submit.prevent="saveFullEditItem"
                >
                    <div
                        v-if="!otherCategoryValues.includes(fullEditingItem.category)"
                    >
                        <label class="mb-2 block text-sm font-black text-slate-800">Category</label>
                        <select
                            v-model="fullEditForm.category"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        >
                            <option value="supplies">Supplies</option>
                            <option value="ict">ICT</option>
                            <option value="furniture">Other Items - Furniture/Fixtures</option>
                            <option value="emergency_kits">Other Items - Emergency Kits</option>
                            <option value="token_giveaways">Other Items - Token and Giveaways</option>
                        </select>
                        <p v-if="fullEditErrors.category" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.category }}</p>
                    </div>

                    <div
                        v-if="!otherCategoryValues.includes(fullEditForm.category)"
                    >
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
                        <label class="mb-2 block text-sm font-black text-slate-800">Item Name</label>
                        <input
                            v-model="fullEditForm.item"
                            type="text"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        />
                        <p v-if="fullEditErrors.item" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.item }}</p>
                    </div>

                    <div
                        v-if="
                            otherCategoryHasCount(
                                fullEditForm.category
                            )
                        "
                        class="sm:col-span-2"
                    >
                        <div
                            class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <label
                                        class="mb-2 block text-sm font-black text-slate-800"
                                    >
                                        Current Count
                                    </label>

                                    <div
                                        class="flex h-11 min-w-[120px] items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-black tabular-nums text-slate-900"
                                    >
                                        {{
                                            fullEditForm.currently_available
                                            || 0
                                        }}
                                    </div>

                                    <p
                                        class="mt-2 text-[10px] font-semibold leading-4 text-slate-500"
                                    >
                                        Current Count is protected from direct editing. Use the Add button when new stock or a new unit arrives.
                                    </p>
                                </div>

                                <button
                                    v-if="!showAddOtherCountField"
                                    type="button"
                                    class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-black text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100"
                                    @click="
                                        showAddOtherCountField = true
                                    "
                                >
                                    <span
                                        class="text-lg leading-none"
                                        aria-hidden="true"
                                    >
                                        +
                                    </span>
                                    {{
                                        isPropertyTrackedOtherCategory(
                                            fullEditForm.category
                                        )
                                            ? 'Add Unit'
                                            : 'Add Stock'
                                    }}
                                </button>

                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-600 transition hover:bg-slate-50"
                                    @click="resetAddOtherCount"
                                >
                                    Cancel Add
                                </button>
                            </div>

                            <div
                                v-if="showAddOtherCountField"
                                class="mt-4 border-t border-blue-100 pt-4"
                            >
                                <!-- FURNITURE / FIXTURES -->
                                <div
                                    v-if="
                                        isPropertyTrackedOtherCategory(
                                            fullEditForm.category
                                        )
                                    "
                                    class="rounded-xl border border-blue-200 bg-white p-4"
                                >
                                    <div
                                        class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p
                                                class="text-xs font-black text-blue-900"
                                            >
                                                New Furniture / Fixture Unit
                                            </p>

                                            <p
                                                class="mt-1 text-[10px] font-semibold leading-4 text-slate-500"
                                            >
                                                Complete the Property Details below. Saving automatically adds 1 to the Current Count.
                                            </p>
                                        </div>

                                        <span
                                            class="self-start rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[10px] font-black text-emerald-700 sm:self-auto"
                                        >
                                            New Count:
                                            {{
                                                Number(
                                                    fullEditForm.currently_available
                                                    || 0
                                                ) + 1
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="grid gap-3 md:grid-cols-3"
                                    >
                                        <div>
                                            <label
                                                class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-blue-700"
                                            >
                                                Description
                                                <span class="text-rose-500">*</span>
                                            </label>

                                            <input
                                                v-model="newOtherAssetForm.description"
                                                type="text"
                                                maxlength="255"
                                                placeholder="Example: Office Chair"
                                                class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-semibold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                                :class="
                                                    fullEditErrors['new_other_asset.description']
                                                        ? 'border-rose-400'
                                                        : 'border-blue-200 focus:border-blue-400'
                                                "
                                            />

                                            <p
                                                v-if="fullEditErrors['new_other_asset.description']"
                                                class="mt-1 text-[10px] font-bold text-rose-600"
                                            >
                                                {{ fullEditErrors['new_other_asset.description'] }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-blue-700"
                                            >
                                                Property Number
                                                <span class="text-rose-500">*</span>
                                            </label>

                                            <input
                                                v-model="newOtherAssetForm.property_number"
                                                type="text"
                                                maxlength="100"
                                                placeholder="Enter property number"
                                                class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-bold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                                :class="
                                                    fullEditErrors['new_other_asset.property_number']
                                                        ? 'border-rose-400'
                                                        : 'border-blue-200 focus:border-blue-400'
                                                "
                                            />

                                            <p
                                                v-if="fullEditErrors['new_other_asset.property_number']"
                                                class="mt-1 text-[10px] font-bold text-rose-600"
                                            >
                                                {{ fullEditErrors['new_other_asset.property_number'] }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-emerald-700"
                                            >
                                                Current User
                                                <span
                                                    class="font-semibold normal-case tracking-normal text-slate-400"
                                                >
                                                    (Optional)
                                                </span>
                                            </label>

                                            <input
                                                v-model="newOtherAssetForm.current_user"
                                                type="text"
                                                maxlength="255"
                                                placeholder="Employee / Office / User"
                                                class="h-10 w-full rounded-lg border border-emerald-200 bg-emerald-50/40 px-3 text-xs font-semibold text-emerald-950 outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- EMERGENCY KITS / TOKEN & GIVEAWAYS -->
                                <div
                                    v-else
                                    class="rounded-xl border border-emerald-100 bg-emerald-50 p-4"
                                >
                                    <label
                                        class="mb-2 block text-[10px] font-black uppercase tracking-[0.08em] text-emerald-700"
                                    >
                                        Quantity to Add
                                    </label>

                                    <input
                                        v-model.number="otherCountToAdd"
                                        type="number"
                                        min="1"
                                        step="1"
                                        placeholder="Example: 5"
                                        class="h-11 w-full rounded-xl border bg-white px-4 text-sm font-black tabular-nums text-slate-900 outline-none focus:ring-4 focus:ring-emerald-100"
                                        :class="
                                            fullEditErrors.add_other_count
                                                ? 'border-rose-400'
                                                : 'border-emerald-200 focus:border-emerald-400'
                                        "
                                    />

                                    <p
                                        v-if="fullEditErrors.add_other_count"
                                        class="mt-2 text-xs font-bold text-rose-600"
                                    >
                                        {{ fullEditErrors.add_other_count }}
                                    </p>

                                    <p
                                        class="mt-2 text-[10px] font-semibold leading-4 text-emerald-700"
                                    >
                                        Saving adds this quantity to the Current Count. Existing stock is not replaced.
                                    </p>

                                    <div
                                        v-if="
                                            Number.isInteger(
                                                Number(otherCountToAdd)
                                            )
                                            && Number(otherCountToAdd) > 0
                                        "
                                        class="mt-3 flex items-center justify-between rounded-lg border border-emerald-200 bg-white px-3 py-2"
                                    >
                                        <span
                                            class="text-[10px] font-bold text-slate-500"
                                        >
                                            New Count
                                        </span>

                                        <span
                                            class="text-sm font-black tabular-nums text-emerald-700"
                                        >
                                            {{
                                                Number(
                                                    fullEditForm.currently_available
                                                    || 0
                                                )
                                                +
                                                Number(otherCountToAdd)
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="otherCategoryValues.includes(fullEditForm.category)"
                        class="sm:col-span-2">
                        <label class="mb-2 block text-sm font-black text-slate-800">Location</label>
                        <input
                            v-model="fullEditForm.location"
                            type="text"
                            placeholder="Example: SPD Library"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        />
                        <p v-if="fullEditErrors.location" class="mt-2 text-xs font-bold text-rose-600">{{ fullEditErrors.location }}</p>
                    </div>

                    <div
                        v-if="!otherCategoryValues.includes(fullEditForm.category)">
                        <label
                            class="mb-2 block text-sm font-black text-slate-800">
                            Unit of Measure
                        </label>

                        <select
                            v-model="fullEditForm.unit"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100">
                            <option
                                v-for="
                                    unitOption in editUnitOptions
                                "
                                :key="
                                    `edit-${fullEditForm.category}-${unitOption.value}`
                                "
                                :value="
                                    unitOption.value
                                "
                            >
                                {{ unitOption.label }}
                            </option>
                        </select>

                        <p
                            v-if="fullEditErrors.unit"
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{ fullEditErrors.unit }}
                        </p>
                    </div>



                    <div
                        v-if="fullEditForm.category === 'ict'"
                        :class="
                            !isIctSubscription(
                                fullEditForm.unit
                            )
                                ? 'sm:col-span-2'
                                : ''
                        "
                    >
                        <template
                            v-if="
                                isIctSubscription(
                                    fullEditForm.unit
                                )
                            "
                        >
                            <label class="mb-2 block text-sm font-black text-slate-800">
                                {{
                                    isIctMonthBased(fullEditForm.unit)
                                        ? 'Subscription Duration (Month/s)'
                                        : 'Subscription Duration (Year/s)'
                                }}
                            </label>

                            <input
                                v-model.number="fullEditForm.currently_available"
                                type="number"
                                min="0"
                                step="1"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-black tabular-nums text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                            />

                            <p
                                v-if="fullEditErrors.currently_available"
                                class="mt-2 text-xs font-bold text-rose-600"
                            >
                                {{ fullEditErrors.currently_available }}
                            </p>
                        </template>

                        <template v-else>
                            <div
                                class="rounded-2xl border border-blue-100 bg-blue-50/50 p-4"
                            >
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                                >
                                    <div class="min-w-0">
                                        <label
                                            class="mb-2 block text-sm font-black text-slate-800"
                                        >
                                            Current Count
                                        </label>

                                        <div
                                            class="flex h-11 min-w-[120px] items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-black tabular-nums text-slate-900"
                                        >
                                            {{
                                                fullEditForm.currently_available
                                                || 0
                                            }}
                                        </div>

                                        <p
                                            class="mt-2 text-[10px] font-semibold leading-4 text-slate-500"
                                        >
                                            Count updates automatically when a new ICT unit is added.
                                        </p>
                                    </div>

                                    <button
                                        v-if="!showAddIctUnitFields"
                                        type="button"
                                        class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-black text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100"
                                        @click="
                                            showAddIctUnitFields = true
                                        "
                                    >
                                        <span
                                            class="text-lg leading-none"
                                            aria-hidden="true"
                                        >
                                            +
                                        </span>
                                        Add ICT Unit
                                    </button>

                                    <button
                                        v-else
                                        type="button"
                                        class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-600 transition hover:bg-slate-50"
                                        @click="resetNewIctAssetForm"
                                    >
                                        Cancel Add
                                    </button>
                                </div>

                                <div
                                    v-if="showAddIctUnitFields"
                                    class="mt-4 border-t border-blue-100 pt-4"
                                >
                                    <div
                                        class="mb-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3"
                                    >
                                        <p
                                            class="text-xs font-black text-emerald-800"
                                        >
                                            New ICT Unit
                                        </p>

                                        <p
                                            class="mt-1 text-[10px] font-semibold leading-5 text-emerald-700"
                                        >
                                            Complete the details below. Saving this item will automatically add 1 to the Current Count.
                                        </p>
                                    </div>

                                    <div
                                        class="grid gap-4 sm:grid-cols-2"
                                    >
                                        <div class="sm:col-span-2">
                                            <label
                                                class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-blue-700"
                                            >
                                                Description
                                                <span class="text-rose-500">*</span>
                                            </label>

                                            <input
                                                v-model="newIctAssetForm.description"
                                                type="text"
                                                maxlength="255"
                                                :placeholder="
                                individualPropertyEditIsOther
                                    ? 'Example: Office Chair, Table, Cabinet'
                                    : 'Example: Laptop, Desktop, Monitor'
                            "
                                                class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-semibold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                                :class="
                                                    fullEditErrors['new_ict_asset.description']
                                                        ? 'border-rose-400'
                                                        : 'border-blue-200 focus:border-blue-400'
                                                "
                                            />

                                            <p
                                                v-if="fullEditErrors['new_ict_asset.description']"
                                                class="mt-1 text-[10px] font-bold text-rose-600"
                                            >
                                                {{ fullEditErrors['new_ict_asset.description'] }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500"
                                            >
                                                Accessories
                                            </label>

                                            <input
                                                v-model="newIctAssetForm.accessories"
                                                type="text"
                                                maxlength="500"
                                                placeholder="Mouse, charger, bag, etc."
                                                class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-blue-700"
                                            >
                                                Property Number
                                                <span class="text-rose-500">*</span>
                                            </label>

                                            <input
                                                v-model="newIctAssetForm.property_number"
                                                type="text"
                                                maxlength="100"
                                                placeholder="Enter Property Number"
                                                class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-bold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                                :class="
                                                    fullEditErrors['new_ict_asset.property_number']
                                                        ? 'border-rose-400'
                                                        : 'border-blue-200 focus:border-blue-400'
                                                "
                                            />

                                            <p
                                                v-if="fullEditErrors['new_ict_asset.property_number']"
                                                class="mt-1 text-[10px] font-bold text-rose-600"
                                            >
                                                {{ fullEditErrors['new_ict_asset.property_number'] }}
                                            </p>
                                        </div>

                                                                                <div class="sm:col-span-2 -mb-1 mt-1">
                                            <p class="text-[9px] font-black uppercase tracking-[0.10em] text-emerald-700">
                                                Assignment & Status
                                            </p>
                                        </div>

<div>
                                            <label
                                                class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-emerald-700"
                                            >
                                                Current User
                                            </label>

                                            <input
                                                v-model="newIctAssetForm.current_user"
                                                type="text"
                                                maxlength="255"
                                                placeholder="Employee / Office / User"
                                                class="h-10 w-full rounded-lg border border-emerald-200 bg-emerald-50/40 px-3 text-xs font-semibold text-emerald-950 outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                                            />
                                        </div>

<div>
                                            <label
                                                class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-emerald-700"
                                            >
                                                Status
                                                <span class="text-rose-500">*</span>
                                            </label>

                                            <select
                                                v-model="newIctAssetForm.status"
                                                class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-bold text-emerald-950 outline-none focus:ring-4 focus:ring-emerald-100"
                                                :class="
                                                    fullEditErrors['new_ict_asset.status']
                                                        ? 'border-rose-400'
                                                        : 'border-emerald-200 bg-emerald-50/40 focus:border-emerald-400'
                                                "
                                            >
                                                <option
                                                    v-for="option in ictAssetStatusOptions"
                                                    :key="`new-item-status-${option.value}`"
                                                    :value="option.value"
                                                >
                                                    {{ option.label }}
                                                </option>
                                            </select>

                                            <p
                                                v-if="fullEditErrors['new_ict_asset.status']"
                                                class="mt-1 text-[10px] font-bold text-rose-600"
                                            >
                                                {{ fullEditErrors['new_ict_asset.status'] }}
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-xl border border-rose-200 bg-rose-50/60 p-3 sm:col-span-2"
                                        >
                                            <p class="mb-3 text-[9px] font-black uppercase tracking-[0.10em] text-blue-600">
                                                Asset Life Cycle
                                            </p>

                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <div>
                                                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-rose-700">
                                                        Date Acquired
                                                    </label>

                                                    <input
                                                        v-model="newIctAssetForm.date_acquired"
                                                        type="date"
                                                        class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-bold text-rose-950 outline-none focus:ring-4 focus:ring-rose-100"
                                                        :class="
                                                            fullEditErrors['new_ict_asset.date_acquired']
                                                                ? 'border-rose-400'
                                                                : 'border-rose-200 bg-white focus:border-rose-400'
                                                        "
                                                    />

                                                    <p
                                                        v-if="fullEditErrors['new_ict_asset.date_acquired']"
                                                        class="mt-1 text-[10px] font-bold text-rose-600"
                                                    >
                                                        {{ fullEditErrors['new_ict_asset.date_acquired'] }}
                                                    </p>
                                                </div>

                                                <div>
                                                    <label class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-rose-700">
                                                        Life Span Ended
                                                    </label>

                                                    <input
                                                        v-model="newIctAssetForm.life_span_ended"
                                                        type="date"
                                                        class="h-10 w-full rounded-lg border bg-white px-3 text-xs font-bold text-rose-950 outline-none focus:ring-4 focus:ring-rose-100"
                                                        :class="
                                                            fullEditErrors['new_ict_asset.life_span_ended']
                                                                ? 'border-rose-400'
                                                                : 'border-rose-200 bg-white focus:border-rose-400'
                                                        "
                                                    />

                                                    <p
                                                        v-if="fullEditErrors['new_ict_asset.life_span_ended']"
                                                        class="mt-1 text-[10px] font-bold text-rose-600"
                                                    >
                                                        {{ fullEditErrors['new_ict_asset.life_span_ended'] }}
                                                    </p>
                                                </div>
                                            </div>

                                            <p class="mt-2 text-[9px] font-semibold leading-4 text-slate-400">
                                                Near For Return warning starts within 365 days of the Life Span Ended date.
                                            </p>
                                        </div>

                                        

                                        <div class="sm:col-span-2">
                                            <label
                                                class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-indigo-700"
                                            >
                                                MR
                                                <span class="font-semibold normal-case tracking-normal text-slate-400">
                                                    (Permanent Employee)
                                                </span>
                                            </label>

                                            <select
                                                v-model="newIctAssetForm.mr_personnel_id"
                                                class="h-10 w-full rounded-lg border border-indigo-200 bg-indigo-50/40 px-3 text-xs font-semibold text-indigo-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                                                @change="
                                                    syncAssetMr(
                                                        newIctAssetForm
                                                    )
                                                "
                                            >
                                                <option value="">
                                                    No MR / Unassigned
                                                </option>

                                                <option
                                                    v-for="person in mrPersonnelOptions"
                                                    :key="`new-item-mr-${person.id}`"
                                                    :value="person.id"
                                                >
                                                    {{ person.name }}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div
                        v-if="
                            isPropertyTrackedOtherCategory(
                                fullEditForm.category
                            )
                        "
                        class="sm:col-span-2 rounded-2xl border border-blue-100 bg-blue-50/40 p-4"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-sm font-black text-slate-900"
                                >
                                    Individual Property Editing
                                </p>

                                <p
                                    class="mt-1 text-xs font-semibold leading-5 text-slate-500"
                                >
                                    Existing Furniture/Fixtures properties are edited one at a time from the accordion, just like ICT. Use Add Unit above only when adding a new property.
                                </p>
                            </div>

                            <span
                                class="shrink-0 rounded-xl border border-blue-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-[0.08em] text-blue-700"
                            >
                                {{
                                    ictAssetDetails(
                                        fullEditingItem
                                    ).length
                                }}
                                Existing
                                {{
                                    ictAssetDetails(
                                        fullEditingItem
                                    ).length === 1
                                        ? 'Property'
                                        : 'Properties'
                                }}
                            </span>
                        </div>
                    </div>


                    <template v-if="fullEditForm.category === 'supplies'">
                        <div class="sm:col-span-2">
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
                    </template>

                    <div
                        v-if="fullEditForm.category === 'supplies'"
                        class="sm:col-span-2"
                    >
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

                    <div
                        v-if="
                            fullEditForm.category === 'supplies'
                            && fullEditForm.quarters.length
                        "
                        class="sm:col-span-2 rounded-2xl border border-blue-100 bg-blue-50/40 p-4"
                    >
                        <p class="text-xs font-black text-slate-800">
                            Current Balance per Quarter
                        </p>

                        <p class="mt-1 text-[10px] font-semibold leading-4 text-slate-500">
                            Existing quarters keep their historical remaining balance. When you add a new quarter, enter only the NEW quantity for that quarter; the previous quarter's remaining stock is carried forward automatically.
                        </p>

                        <p
                            v-if="
                                fullEditingItem
                                && !hasQuarterStock(fullEditingItem)
                                && fullEditForm.quarters.length > 1
                            "
                            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[10px] font-bold leading-4 text-amber-800"
                        >
                            Legacy multi-quarter record: the old global balance cannot be split automatically. Enter the correct remaining balance for each quarter once, then future releases will be tracked separately.
                        </p>

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div
                                v-for="quarter in fullEditForm.quarters"
                                :key="`edit-quarter-stock-${quarter}`"
                                class="rounded-xl border border-slate-200 bg-white p-3"
                            >
                                <label class="mb-2 block text-xs font-black text-slate-800">
                                    {{ quarter.toUpperCase() }}
                                    ·
                                    {{
                                        isEditQuarterNew(quarter)
                                            ? (
                                                'New Quantity to Add'
                                            )
                                            : (
                                                'Remaining Balance'
                                            )
                                    }}
                                </label>

                                <input
                                    :value="
                                        quarterStockFormCurrent(
                                            fullEditForm,
                                            quarter
                                        )
                                    "
                                    type="number"
                                    min="0"
                                    step="1"
                                    :placeholder="
                                        isEditQuarterNew(quarter)
                                            ? 'Enter new quantity for this quarter'
                                            : 'Enter remaining balance'
                                    "
                                    class="h-11 w-full rounded-xl border bg-white px-3 text-sm font-black tabular-nums text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                                    :class="
                                        fullEditErrors[
                                            `quarter_stock.${quarter}.current`
                                        ]
                                            ? 'border-rose-400'
                                            : 'border-slate-200 focus:border-blue-400'
                                    "
                                    @input="
                                        setEditQuarterStockCurrent(
                                            quarter,
                                            $event.target.value
                                        )
                                    "
                                />

                                <p
                                    v-if="
                                        fullEditErrors[
                                            `quarter_stock.${quarter}.current`
                                        ]
                                    "
                                    class="mt-2 text-[10px] font-bold text-rose-600"
                                >
                                    {{
                                        fullEditErrors[
                                            `quarter_stock.${quarter}.current`
                                        ]
                                    }}
                                </p>

                                <p
                                    v-if="
                                        fullEditingItem
                                        && normalizeQuarterStock(
                                            fullEditingItem.quarter_stock
                                        )?.[quarter]
                                    "
                                    class="mt-2 text-[9px] font-semibold text-slate-400"
                                >
                                    Released in {{ quarter.toUpperCase() }}:
                                    {{
                                        normalizeQuarterStock(
                                            fullEditingItem.quarter_stock
                                        )[quarter].released
                                    }}
                                </p>


                                <div
                                    v-if="isEditQuarterNew(quarter)"
                                    class="mt-2 rounded-lg bg-slate-50 px-2.5 py-2 text-[9px] font-semibold leading-4 text-slate-500"
                                >
                                    <p>
                                        Carryover from previous quarter:
                                        <strong class="text-slate-700">
                                            {{
                                                projectedQuarterCarryover(
                                                    fullEditForm,
                                                    quarter,
                                                    originalEditQuarterStock
                                                )
                                            }}
                                        </strong>
                                    </p>

                                    <p>
                                        New resulting balance:
                                        <strong class="text-emerald-700">
                                            {{
                                                projectedFormQuarterCurrent(
                                                    fullEditForm,
                                                    quarter,
                                                    originalEditQuarterStock
                                                )
                                            }}
                                        </strong>
                                    </p>
                                </div>

                                <div class="mt-3 border-t border-slate-100 pt-3">
                                    <label class="mb-2 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500">
                                        {{ quarter.toUpperCase() }} Remarks
                                        <span class="font-semibold normal-case tracking-normal text-slate-400">
                                            (Optional)
                                        </span>
                                    </label>

                                    <textarea
                                        :value="quarterStockFormRemarks(fullEditForm, quarter)"
                                        rows="2"
                                        maxlength="1000"
                                        :placeholder="`Remarks for ${quarter.toUpperCase()}...`"
                                        class="w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold leading-5 text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                        @input="
                                            setEditQuarterStockRemarks(
                                                quarter,
                                                $event.target.value
                                            )
                                        "
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div
                        v-if="fullEditForm.category === 'supplies'"
                        class="sm:col-span-2 rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3"
                    >
                        <p class="text-[9px] font-black uppercase tracking-[0.12em] text-blue-500">Quantity Released</p>
                        <p class="mt-1 text-xs font-semibold leading-5 text-blue-800">
                            Automatic/read-only. Use the separate <strong>Release</strong> action to release stock.
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-2 block text-sm font-black text-slate-800">
                            {{
                                fullEditForm.category === 'supplies'
                                    ? 'General Remarks'
                                    : 'Remarks'
                            }}
                        </label>
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

        <!-- EDIT ONE PROPERTY ROW: ICT / FURNITURE / FIXTURES -->
        <div
            v-if="
                canManageInventory
                && showIctAssetEditModal
                && ictAssetEditingItem
            "
            class="fixed inset-0 z-[75] flex items-end justify-center bg-slate-950/55 p-0 backdrop-blur-sm sm:items-center sm:p-4"
            @click.self="closeIctAssetEditModal"
        >
            <div
                class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-[2rem] bg-white shadow-2xl sm:rounded-3xl"
            >
                <div
                    class="sticky top-0 z-10 border-b border-slate-200 bg-white px-5 py-5 sm:px-6"
                >
                    <div
                        class="flex items-start justify-between gap-4"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.15em] text-blue-600"
                            >
                                Edit Property Detail
                            </p>

                            <h3
                                class="mt-1 break-words text-xl font-black text-slate-900"
                            >
                                {{
                                    ictAssetEditingItem.item
                                    || 'ICT Item'
                                }}
                            </h3>

                            <p
                                class="mt-1 text-xs font-semibold text-slate-500"
                            >
                                {{
                                    individualPropertyEditIsOther
                                        ? 'Editing one Furniture/Fixtures property only.'
                                        : 'Editing one ICT unit only.'
                                }}
                                Property Row
                                {{
                                    Number(
                                        ictAssetEditingIndex
                                    ) + 1
                                }}
                            </p>
                        </div>

                        <div
                            class="flex shrink-0 items-center gap-2"
                        >
                            <button
                                type="button"
                                title="View History"
                                aria-label="View History"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-slate-200"
                                @click="
                                    openHistoryModal(
                                        ictAssetEditingItem,
                                        {
                                            property_number:
                                                ictAssetEditingOriginalPropertyNumber
                                                || ictAssetEditForm.property_number,
                                            description:
                                                ictAssetEditForm.description,
                                        }
                                    )
                                "
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

                            <button
                                type="button"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-lg font-bold text-slate-500 transition hover:bg-slate-50"
                                @click="closeIctAssetEditModal"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                </div>

                <form
                    class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6"
                    @submit.prevent="saveIctAssetEdit"
                >
                    <div class="sm:col-span-2">
                        <label
                            class="mb-2 block text-sm font-black text-slate-800"
                        >
                            Description
                            <span class="text-rose-500">*</span>
                        </label>

                        <input
                            v-model="ictAssetEditForm.description"
                            type="text"
                            maxlength="255"
                            placeholder="Example: Laptop, Desktop, Monitor"
                            class="h-11 w-full rounded-xl border bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                            :class="
                                ictAssetEditErrors.description
                                    || ictAssetEditErrors['asset.description']
                                    ? 'border-rose-400'
                                    : 'border-blue-200 focus:border-blue-400'
                            "
                        />

                        <p
                            v-if="
                                ictAssetEditErrors.description
                                || ictAssetEditErrors['asset.description']
                            "
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{
                                ictAssetEditErrors.description
                                || ictAssetEditErrors['asset.description']
                            }}
                        </p>
                    </div>

                    <div v-if="individualPropertyEditIsIct">
                        <label
                            class="mb-2 block text-sm font-black text-slate-800"
                        >
                            Accessories
                        </label>

                        <input
                            v-model="ictAssetEditForm.accessories"
                            type="text"
                            maxlength="500"
                            placeholder="Mouse, charger, bag, etc."
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-2 block text-sm font-black text-slate-800"
                        >
                            Property Number
                            <span class="text-rose-500">*</span>
                        </label>

                        <input
                            v-model="ictAssetEditForm.property_number"
                            type="text"
                            maxlength="100"
                            placeholder="Enter Property Number"
                            class="h-11 w-full rounded-xl border bg-white px-3 text-sm font-bold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100"
                            :class="
                                ictAssetEditErrors.property_number
                                    || ictAssetEditErrors['asset.property_number']
                                    ? 'border-rose-400'
                                    : 'border-blue-200 focus:border-blue-400'
                            "
                        />

                        <p
                            v-if="
                                ictAssetEditErrors.property_number
                                || ictAssetEditErrors['asset.property_number']
                            "
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{
                                ictAssetEditErrors.property_number
                                || ictAssetEditErrors['asset.property_number']
                            }}
                        </p>
                    </div>

                    <div
                        v-if="individualPropertyEditIsIct"
                        class="sm:col-span-2 -mb-1 mt-1"
                    >
                        <p class="text-[10px] font-black uppercase tracking-[0.10em] text-emerald-700">
                            Assignment & Status
                        </p>
                    </div>

<div>
                        <label
                            class="mb-2 block text-sm font-black text-emerald-950"
                        >
                            Current User
                        </label>

                        <input
                            v-model="ictAssetEditForm.current_user"
                            type="text"
                            maxlength="255"
                            placeholder="Employee / Office / User"
                            class="h-11 w-full rounded-xl border border-emerald-200 bg-emerald-50/40 px-3 text-sm font-semibold text-emerald-950 outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                        />
                    </div>

<div v-if="individualPropertyEditIsIct">
                        <label
                            class="mb-2 block text-sm font-black text-emerald-950"
                        >
                            Status
                            <span class="text-rose-500">*</span>
                        </label>

                        <select
                            v-model="ictAssetEditForm.status"
                            class="h-11 w-full rounded-xl border bg-white px-3 text-sm font-bold text-emerald-950 outline-none focus:ring-4 focus:ring-emerald-100"
                            :class="
                                ictAssetEditErrors.status
                                    || ictAssetEditErrors['asset.status']
                                    ? 'border-rose-400'
                                    : 'border-emerald-200 bg-emerald-50/40 focus:border-emerald-400'
                            "
                        >
                            <option
                                v-for="option in ictAssetStatusOptions"
                                :key="`single-edit-status-${option.value}`"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="
                                ictAssetEditErrors.status
                                || ictAssetEditErrors['asset.status']
                            "
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{
                                ictAssetEditErrors.status
                                || ictAssetEditErrors['asset.status']
                            }}
                        </p>
                    </div>

                    <div
                        v-if="individualPropertyEditIsIct"
                        class="rounded-2xl border border-rose-200 bg-rose-50/60 p-4 sm:col-span-2"
                    >
                        <p class="mb-3 text-[10px] font-black uppercase tracking-[0.10em] text-rose-700">
                            Asset Life Cycle
                        </p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-black text-rose-950">
                                    Date Acquired
                                </label>

                                <input
                                    v-model="ictAssetEditForm.date_acquired"
                                    type="date"
                                    class="h-11 w-full rounded-xl border bg-white px-3 text-sm font-bold text-rose-950 outline-none focus:ring-4 focus:ring-rose-100"
                                    :class="
                                        ictAssetEditErrors.date_acquired
                                            || ictAssetEditErrors['asset.date_acquired']
                                            ? 'border-rose-400'
                                            : 'border-rose-200 bg-white focus:border-rose-400'
                                    "
                                />

                                <p
                                    v-if="
                                        ictAssetEditErrors.date_acquired
                                        || ictAssetEditErrors['asset.date_acquired']
                                    "
                                    class="mt-2 text-xs font-bold text-rose-600"
                                >
                                    {{
                                        ictAssetEditErrors.date_acquired
                                        || ictAssetEditErrors['asset.date_acquired']
                                    }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-black text-rose-950">
                                    Life Span Ended
                                </label>

                                <input
                                    v-model="ictAssetEditForm.life_span_ended"
                                    type="date"
                                    class="h-11 w-full rounded-xl border bg-white px-3 text-sm font-bold text-rose-950 outline-none focus:ring-4 focus:ring-rose-100"
                                    :class="
                                        ictAssetEditErrors.life_span_ended
                                            || ictAssetEditErrors['asset.life_span_ended']
                                            ? 'border-rose-400'
                                            : 'border-rose-200 bg-white focus:border-rose-400'
                                    "
                                />

                                <p
                                    v-if="
                                        ictAssetEditErrors.life_span_ended
                                        || ictAssetEditErrors['asset.life_span_ended']
                                    "
                                    class="mt-2 text-xs font-bold text-rose-600"
                                >
                                    {{
                                        ictAssetEditErrors.life_span_ended
                                        || ictAssetEditErrors['asset.life_span_ended']
                                    }}
                                </p>
                            </div>
                        </div>

                        <p class="mt-3 text-[10px] font-semibold leading-4 text-slate-400">
                            These dates are kept in the property record but are not shown as table columns. The row turns red when 365 days or less remain.
                        </p>
                    </div>

                    

                    <div
                        v-if="individualPropertyEditIsIct"
                        class="sm:col-span-2"
                    >
                        <label
                            class="mb-2 block text-sm font-black text-indigo-950"
                        >
                            MR
                            <span
                                class="font-medium text-slate-400"
                            >
                                (Permanent Employee)
                            </span>
                        </label>

                        <select
                            v-model="ictAssetEditForm.mr_personnel_id"
                            class="h-11 w-full rounded-xl border border-indigo-200 bg-indigo-50/40 px-3 text-sm font-semibold text-indigo-950 outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                            @change="
                                syncAssetMr(
                                    ictAssetEditForm
                                )
                            "
                        >
                            <option value="">
                                No MR / Unassigned
                            </option>

                            <option
                                v-for="person in mrPersonnelOptions"
                                :key="`single-edit-mr-${person.id}`"
                                :value="person.id"
                            >
                                {{ person.name }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:col-span-2 sm:flex-row sm:justify-end"
                    >
                        <button
                            type="button"
                            :disabled="ictAssetEditProcessing"
                            class="h-11 rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                            @click="closeIctAssetEditModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="ictAssetEditProcessing"
                            class="h-11 rounded-xl bg-blue-600 px-6 text-sm font-black text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                ictAssetEditProcessing
                                    ? 'Saving...'
                                    : 'Save Property'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RELEASE ITEM MODAL -->
        <div
            v-if="canManageInventory && showReleaseItemModal"
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

                            <span v-if="releaseIsSupplies">
                                Fixed Value:
                                <strong class="text-slate-800">
                                    {{ releaseHasFixedBaseline ? releaseFixedValue : '—' }}
                                </strong>
                            </span>

                            <span v-else-if="releaseIsIct">
                                {{ ictQuantityLabel(releasingItem) }} tracking
                            </span>

                            <span v-else>
                                Count tracking
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
                                {{
                                    releaseIsIctAsset
                                        ? 'Total Count'
                                        : (
                                            releaseIsIct
                                                ? `${releaseQuantityLabel} Released`
                                                : 'Quantity Released'
                                        )
                                }}
                            </p>

                            <p
                                class="mt-1 text-2xl font-black tabular-nums text-blue-700"
                            >
                                {{
                                    releaseIsIctAsset
                                        ? (
                                            releasingItem?.currently_available
                                            ?? 0
                                        )
                                        : (
                                            releaseTotalReleased
                                            ?? '—'
                                        )
                                }}
                            </p>

                            <p
                                v-if="
                                    !releaseHasFixedBaseline
                                    && !releaseIsIctAsset
                                "
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
                                {{ releaseCurrentLabel }}
                            </p>

                            <p
                                class="mt-1 text-2xl font-black tabular-nums text-emerald-700"
                            >
                                {{ releaseCurrentAvailable }}
                            </p>
                        </div>
                    </div>

                    <!-- ICT PROPERTY TO RELEASE -->
                    <div v-if="releaseIsIctAsset">
                        <div class="mb-2 flex items-end justify-between gap-3">
                            <label class="block text-sm font-black text-slate-800">
                                Available Property Number
                            </label>
                            <span class="text-[10px] font-bold text-slate-400">
                                {{ releaseAvailableProperties.length }} available
                            </span>
                        </div>

                        <select
                            v-model="releaseItemForm.releasePropertyNumber"
                            class="h-12 w-full rounded-xl border bg-white px-4 text-sm font-black text-slate-900 outline-none transition focus:ring-4 focus:ring-blue-100"
                            :class="
                                releaseItemErrors.releasePropertyNumber
                                    ? 'border-rose-400'
                                    : 'border-slate-200 focus:border-blue-400'
                            "
                        >
                            <option value="" disabled>Select available property number</option>
                            <option
                                v-for="asset in releaseAvailableProperties"
                                :key="`release-property-${asset.property_number}`"
                                :value="asset.property_number"
                            >
                                {{ asset.property_number }}
                            </option>
                        </select>

                        <p
                            v-if="releaseItemErrors.releasePropertyNumber"
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{ releaseItemErrors.releasePropertyNumber }}
                        </p>
                    </div>

                    <!-- QUANTITY / DURATION TO RELEASE -->
                    <div v-else>
                        <div class="mb-2 flex items-end justify-between gap-3">
                            <label class="block text-sm font-black text-slate-800">
                                {{ releaseActionLabel }}
                            </label>
                            <span class="text-[10px] font-bold text-slate-400">
                                Max: {{ releaseCurrentAvailable }}
                            </span>
                        </div>

                        <input
                            v-model="releaseItemForm.releaseQuantity"
                            type="number"
                            min="1"
                            :max="releaseCurrentAvailable"
                            step="1"
                            :placeholder="
                                releaseIsIct
                                    ? `Enter ${releaseQuantityLabel.toLowerCase()} to release`
                                    : 'Enter quantity to release'
                            "
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
                            {{ releaseItemErrors.releaseQuantity }}
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
                                    {{
                                        releaseIsIct
                                            ? `${releaseQuantityLabel} Released`
                                            : 'Quantity Released'
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-lg font-black tabular-nums text-slate-900"
                                >
                                    {{
                                        releaseIsIctAsset
                                            ? (
                                                releaseItemForm.releasePropertyNumber
                                                || '—'
                                            )
                                            : (
                                                releaseTotalReleasedAfter
                                                ?? '—'
                                            )
                                    }}
                                </p>
                            </div>

                            <div>
                                <p
                                    class="text-[10px] font-bold text-slate-500"
                                >
                                    {{ releaseCurrentLabel }}
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

                    <!-- RELEASED TO / DESTINATION -->
                    <div v-if="!releaseIsSupplies">
                        <label
                            class="mb-2 block text-sm font-black text-slate-800"
                        >
                            {{
                                releaseIsIctAsset
                                    ? 'Current User / Released To'
                                    : 'Released To / Destination'
                            }}
                        </label>

                        <input
                            v-model="releaseItemForm.releaseDestination"
                            type="text"
                            :placeholder="
                                releaseIsIctAsset
                                    ? 'Example: Juan Dela Cruz'
                                    : 'Example: SPD Library, MIS Staff, Conference Room'
                            "
                            class="h-12 w-full rounded-xl border bg-white px-4 text-sm font-semibold text-slate-800 outline-none transition focus:ring-4 focus:ring-blue-100"
                            :class="
                                releaseItemErrors.releaseDestination
                                    ? 'border-rose-400'
                                    : 'border-slate-200 focus:border-blue-400'
                            "
                        />

                        <p
                            v-if="releaseItemErrors.releaseDestination"
                            class="mt-2 text-xs font-bold text-rose-600"
                        >
                            {{ releaseItemErrors.releaseDestination }}
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
                                || (
                                    releaseIsIctAsset
                                    && (
                                        !String(
                                            releaseItemForm.releasePropertyNumber
                                            || ''
                                        ).trim()
                                        || !String(
                                            releaseItemForm.releaseMrPersonnelId
                                            || ''
                                        ).trim()
                                    )
                                )
                            "
                        >
                            Release Item
                        </button>
                    </div>
                </form>
            </div>
        </div>


        <!-- HISTORY MODAL -->
        <!-- DELETE ITEM CONFIRMATION MODAL -->
        <div
            v-if="canManageInventory && showDeleteItemModal && deletingItem"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
            @click.self="closeDeleteItemModal"
        >
            <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
                <div class="border-b border-rose-100 bg-rose-50 px-5 py-5 sm:px-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-700">
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
                                <path d="M3 6h18" />
                                <path d="M8 6V4h8v2" />
                                <path d="M19 6l-1 14H6L5 6" />
                                <path d="M10 11v5" />
                                <path d="M14 11v5" />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.15em] text-rose-600">
                                Delete Inventory Item
                            </p>

                            <h3 class="mt-1 break-words text-lg font-black text-slate-900">
                                {{ deletingItem.item || 'Inventory Item' }}
                            </h3>
                        </div>
                    </div>
                </div>

                <div class="p-5 sm:p-6">
                    <p class="text-sm font-semibold leading-6 text-slate-600">
                        Are you sure you want to permanently delete this inventory item?
                    </p>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            :disabled="deleteItemProcessing"
                            class="h-11 rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="closeDeleteItemModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            :disabled="deleteItemProcessing"
                            class="h-11 rounded-xl bg-rose-600 px-6 text-sm font-black text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="confirmDeleteItem"
                        >
                            {{ deleteItemProcessing ? 'Deleting...' : 'Delete Item' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showHistoryModal"
            class="fixed inset-0 z-[95] flex items-center justify-center bg-slate-950/50 p-3 backdrop-blur-sm sm:p-4"
            @click.self="closeHistoryModal"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-[1.75rem] border border-slate-300 bg-slate-50 shadow-2xl"
            >
                <!-- HISTORY HEADER -->
                <div
                    class="shrink-0 border-b border-blue-200 bg-blue-50 px-5 py-5 sm:px-6"
                >
                    <div
                        class="flex items-start justify-between gap-4"
                    >
                        <div class="min-w-0">
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <p
                                    class="text-[10px] font-black uppercase tracking-[0.16em] text-blue-700"
                                >
                                    {{
                                        historyIsPropertyScoped
                                            ? 'Property History'
                                            : 'Inventory History'
                                    }}
                                </p>

                                <span
                                    v-if="historyIsPropertyScoped"
                                    class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-[9px] font-black text-blue-700"
                                >
                                    {{ historyPropertyNumber }}
                                </span>
                            </div>

                            <h3
                                class="mt-1 break-words text-xl font-black text-slate-900"
                            >
                                {{
                                    historyItem?.item
                                    || 'Inventory Item'
                                }}
                            </h3>

                            <p
                                v-if="
                                    historyIsPropertyScoped
                                    && historyPropertyDescription
                                "
                                class="mt-1 text-xs font-semibold text-slate-500"
                            >
                                {{ historyPropertyDescription }}
                            </p>

                            <div
                                v-if="!historyIsPropertyScoped"
                                class="mt-3 flex flex-wrap gap-2"
                            >
                                <span
                                    class="rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-[10px] font-bold text-emerald-700"
                                >
                                    Current:
                                    <strong
                                        class="ml-1 font-black tabular-nums"
                                    >
                                        {{
                                            currentAvailableValue(
                                                historyItem
                                            )
                                            ?? '—'
                                        }}
                                    </strong>
                                </span>

                                <span
                                    class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-[10px] font-bold text-blue-700"
                                >
                                    Released:
                                    <strong
                                        class="ml-1 font-black tabular-nums"
                                    >
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
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-blue-200 bg-white text-lg font-black text-blue-700 shadow-sm transition hover:bg-blue-100"
                            @click="closeHistoryModal"
                        >
                            ×
                        </button>
                    </div>

                    <div
                        class="mt-4 rounded-xl border border-blue-200 bg-white px-4 py-3 shadow-sm"
                    >
                        <p
                            class="text-[10px] font-semibold leading-5 text-slate-500"
                        >
                            Newest activity appears first.
                            <span
                                v-if="historyIsPropertyScoped"
                                class="font-black text-slate-700"
                            >
                                Only changes for Property
                                {{ historyPropertyNumber }} are shown.
                            </span>
                        </p>
                    </div>
                </div>

                <!-- HISTORY BODY -->
                <div
                    class="min-h-0 flex-1 overflow-y-auto bg-slate-100"
                >
                    <div
                        v-if="historyLoading"
                        class="flex min-h-60 flex-col items-center justify-center px-6 text-center"
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

                    <div
                        v-else-if="
                            !visibleInventoryHistories.length
                        "
                        class="flex min-h-64 flex-col items-center justify-center px-6 text-center"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"
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
                            class="mt-1 max-w-sm text-xs font-semibold leading-5 text-slate-400"
                        >
                            {{
                                historyIsPropertyScoped
                                    ? 'No recorded changes were found for this Property Number.'
                                    : 'Edits, added units, and releases will appear here.'
                            }}
                        </p>
                    </div>

                    <!-- ACTIVITY LIST -->
                    <div
                        v-else
                        class="space-y-3 p-4 sm:p-5"
                    >
                        <article
                            v-for="history in visibleInventoryHistories"
                            :key="`inventory-history-${history.id}`"
                            class="overflow-hidden rounded-2xl border border-slate-300 bg-white shadow-md"
                        >
                            <!-- AT-A-GLANCE SUMMARY -->
                            <div
                                class="border-b border-blue-100 bg-blue-50/60 px-4 py-4 sm:px-5"
                            >
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div
                                        class="min-w-0"
                                    >
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-[0.08em]"
                                            :class="
                                                historyActionToneClass(
                                                    history
                                                )
                                            "
                                        >
                                            {{
                                                historyActionLabel(
                                                    history
                                                )
                                            }}
                                        </span>

                                        <p
                                            v-if="
                                                historyEventSummary(
                                                    history
                                                )
                                            "
                                            class="mt-2 break-words text-sm font-black leading-5 text-slate-900"
                                        >
                                            {{
                                                historyEventSummary(
                                                    history
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <div
                                        class="shrink-0 text-left sm:text-right"
                                    >
                                        <p
                                            class="text-[10px] font-black text-slate-700"
                                        >
                                            {{
                                                history.updated_by_name
                                                || 'Unknown User'
                                            }}
                                        </p>

                                        <p
                                            class="mt-0.5 text-[10px] font-semibold text-slate-400"
                                        >
                                            {{
                                                history.created_at
                                                || '—'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- WHAT CHANGED -->
                            <div
                                class="px-4 py-4 sm:px-5"
                            >
                                <p
                                    class="mb-3 text-[9px] font-black uppercase tracking-[0.12em] text-slate-400"
                                >
                                    What Changed
                                </p>

                                <div
                                    v-if="
                                        historyHasDetailedChanges(
                                            history
                                        )
                                    "
                                    class="space-y-2"
                                >
                                    <div
                                        v-for="(change, changeIndex) in history.changes"
                                        :key="`history-${history.id}-change-${changeIndex}`"
                                        class="rounded-xl border border-slate-200 bg-slate-100 px-3 py-3"
                                    >
                                        <p
                                            class="text-[10px] font-black leading-4 text-slate-700"
                                        >
                                            {{ change.label }}
                                        </p>

                                        <div
                                            v-if="change.single"
                                            class="mt-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2"
                                        >
                                            <p
                                                class="break-words text-xs font-black leading-5 text-blue-700"
                                            >
                                                {{
                                                    historyChangeValue(
                                                        change,
                                                        change.new
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div
                                            v-else
                                            class="mt-2 grid gap-2 sm:grid-cols-[1fr_32px_1fr]"
                                        >
                                            <div
                                                class="rounded-lg border border-slate-300 bg-slate-200/70 px-3 py-2"
                                            >
                                                <p
                                                    class="text-[8px] font-black uppercase tracking-[0.1em] text-slate-400"
                                                >
                                                    Before
                                                </p>

                                                <p
                                                    class="mt-1 break-words text-xs font-semibold leading-5 text-slate-600"
                                                >
                                                    {{
                                                        historyChangeValue(
                                                            change,
                                                            change.old
                                                        )
                                                    }}
                                                </p>
                                            </div>

                                            <div
                                                class="hidden items-center justify-center text-sm font-black text-slate-300 sm:flex"
                                            >
                                                →
                                            </div>

                                            <div
                                                class="rounded-lg border border-blue-300 bg-blue-100/70 px-3 py-2"
                                            >
                                                <p
                                                    class="text-[8px] font-black uppercase tracking-[0.1em] text-blue-400"
                                                >
                                                    After
                                                </p>

                                                <p
                                                    class="mt-1 break-words text-xs font-black leading-5 text-blue-700"
                                                >
                                                    {{
                                                        historyChangeValue(
                                                            change,
                                                            change.new
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- LEGACY HISTORY -->
                                <div
                                    v-else
                                    class="rounded-xl border border-slate-200 bg-slate-100 px-3 py-3"
                                >
                                    <p
                                        v-if="
                                            historyReleasedChanged(
                                                history
                                            )
                                        "
                                        class="text-xs font-semibold text-slate-600"
                                    >
                                        {{
                                            historyQuantityLabel(
                                                history
                                            )
                                        }}:
                                        <strong
                                            class="ml-1 font-black text-blue-700"
                                        >
                                            {{
                                                historyQuantityDisplay(
                                                    history
                                                )
                                            }}
                                        </strong>
                                    </p>

                                    <p
                                        v-if="
                                            historyRemarksChanged(
                                                history
                                            )
                                        "
                                        class="mt-1 text-xs font-semibold text-slate-600"
                                    >
                                        Remarks:
                                        <strong
                                            class="ml-1 text-slate-800"
                                        >
                                            {{
                                                historyRemarksText(
                                                    history
                                                )
                                            }}
                                        </strong>
                                    </p>

                                    <p
                                        v-if="
                                            !historyReleasedChanged(
                                                history
                                            )
                                            && !historyRemarksChanged(
                                                history
                                            )
                                        "
                                        class="text-xs font-semibold text-slate-500"
                                    >
                                        Inventory record updated.
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div
                    class="shrink-0 border-t border-slate-300 bg-slate-50 px-5 py-3 sm:px-6"
                >
                    <button
                        type="button"
                        class="h-10 w-full rounded-xl bg-blue-600 text-xs font-black text-white transition hover:bg-blue-700"
                        @click="closeHistoryModal"
                    >
                        Close History
                    </button>
                </div>
            </div>
        </div>




    </div>
</template>

<style>
.inventory-comfort-theme {
    background:
        linear-gradient(
            180deg,
            #e8eef5 0%,
            #edf2f7 46%,
            #e7edf4 100%
        ) !important;
    color: #172033;
}

/* Main cards and neutral buttons are no longer pure white. */
.inventory-comfort-theme .bg-white {
    background-color: #f7f9fc !important;
}

.inventory-comfort-theme .bg-slate-50 {
    background-color: #edf2f7 !important;
}

/* Slightly stronger borders make cards, rows, and controls easier to separate. */
.inventory-comfort-theme .border-slate-100 {
    border-color: #d4dde7 !important;
}

.inventory-comfort-theme .border-slate-200 {
    border-color: #becbd8 !important;
}

.inventory-comfort-theme .divide-slate-100 > :not([hidden]) ~ :not([hidden]) {
    border-color: #d4dde7 !important;
}

/* Improve secondary-text contrast for older eyes. */
.inventory-comfort-theme .text-slate-300 {
    color: #7b8ba0 !important;
}

.inventory-comfort-theme .text-slate-400 {
    color: #64748b !important;
}

.inventory-comfort-theme .text-slate-500 {
    color: #4b5f76 !important;
}

.inventory-comfort-theme .text-slate-600 {
    color: #334a62 !important;
}

/* Controls use a soft gray-blue fill instead of bright white. */
.inventory-comfort-theme
    input:not([type="checkbox"]):not([type="radio"]),
.inventory-comfort-theme select,
.inventory-comfort-theme textarea {
    background-color: #f1f5f9 !important;
    border-color: #b8c6d4 !important;
    color: #1e293b !important;
}

.inventory-comfort-theme
    input:not([type="checkbox"]):not([type="radio"])::placeholder,
.inventory-comfort-theme textarea::placeholder {
    color: #64748b !important;
}

/* Stronger keyboard/focus visibility. */
.inventory-comfort-theme input:focus,
.inventory-comfort-theme select:focus,
.inventory-comfort-theme textarea:focus,
.inventory-comfort-theme button:focus-visible {
    outline: 2px solid #3b82f6 !important;
    outline-offset: 2px;
}

/* Ledger area gets a calm neutral background. */
.inventory-comfort-theme .inventory-ledger-surface {
    background-color: #f5f8fb !important;
    border-color: #b8c6d4 !important;
    box-shadow:
        0 10px 28px rgba(51, 65, 85, 0.08);
}

/* Keep headers blue, but use a deeper less-glary blue. */
.inventory-comfort-theme .inventory-ledger-table thead {
    background-color: #35679b !important;
}

.inventory-comfort-theme .inventory-ledger-table thead th {
    background-color: #35679b !important;
    border-color: #2d5b8c !important;
    color: #ffffff !important;
}

/* Main data rows use soft blue-gray rather than pure white. */
.inventory-comfort-theme .inventory-ledger-table tbody > tr {
    border-color: #d4dde7;
}

.inventory-comfort-theme
    .inventory-ledger-table
    tbody
    > tr.bg-white {
    background-color: #f7f9fc !important;
}

.inventory-comfort-theme
    .inventory-ledger-table
    tbody
    > tr.bg-white:hover {
    background-color: #e8f1fb !important;
}

.inventory-comfort-theme
    .inventory-ledger-table
    tbody
    > tr.bg-blue-50\/80,
.inventory-comfort-theme
    .inventory-ledger-table
    tbody
    > tr.bg-blue-100\/70,
.inventory-comfort-theme
    .inventory-ledger-table
    tbody
    > tr.bg-blue-50\/45 {
    background-color: #e8f1fb !important;
}

.inventory-comfort-theme .inventory-ledger-table .border-2.border-blue-200 {
    border-color: #adc4dc !important;
    background-color: #f3f7fb !important;
    box-shadow:
        0 8px 20px rgba(51, 65, 85, 0.08);
}

.inventory-comfort-theme .bg-blue-50 {
    background-color: #e7f0fa !important;
}

.inventory-comfort-theme .border-blue-100 {
    border-color: #c5d9ec !important;
}

.inventory-comfort-theme .border-blue-200 {
    border-color: #a9c7e4 !important;
}

.inventory-comfort-theme .cursor-default {
    cursor: default !important;
}

.inventory-comfort-theme button:not(:disabled) {
    cursor: pointer;
}

</style>
