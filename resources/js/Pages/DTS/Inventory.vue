<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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

    purchaseRequestValidations: {
        type: Object,
        default: () => ({}),
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
const currentPage = ref(1)

const perPage = 8


const prValidationCategory = ref('supplies')
const prValidationReturnTab = ref('supplies')
const prValidationReturnOtherCategory = ref('furniture_fixtures')
const prValidationYear = ref(2026)
const prValidationFileInput = ref(null)
const prValidationUploading = ref(false)
const prValidationUploadError = ref('')
const prValidationSearch = ref('')
const prValidationStatusFilter = ref('all')

const purchaseRequestCategoryLabel = (category) => {
    return {
        supplies: 'Supplies',
        ict: 'ICT',
        furniture_fixtures: 'Furniture/Fixtures',
        emergency_kits: 'Emergency Kits',
        token_giveaways: 'Token and Giveaways',
    }[category] || 'Inventory'
}

const prValidationRequiresYear = computed(() =>
    ['supplies', 'ict'].includes(
        prValidationCategory.value
    )
)

const prValidationUsesUnit = computed(() =>
    ['supplies', 'ict'].includes(
        prValidationCategory.value
    )
)

const prValidationUsesQuarter = computed(() =>
    prValidationCategory.value === 'supplies'
)

const prValidationRequiredFields = computed(() => {
    if (prValidationCategory.value === 'supplies') {
        return 'Item | Unit | Quarter | Quantity'
    }

    if (prValidationCategory.value === 'ict') {
        return 'Item | Unit | Quantity'
    }

    return 'Item | Quantity'
})

const emptyPurchaseRequestValidation = (category) => ({
    has_file: false,
    file: null,
    context: {
        validation_category: category,
        category_label:
            purchaseRequestCategoryLabel(
                category
            ),
        inventory_year: null,
    },
    summary: {
        total: 0,
        match: 0,
        not_match: 0,
        not_found: 0,
    },
    rows: [],
    error: null,
})

const prValidationData = computed(() => {
    const category =
        prValidationCategory.value

    return (
        props.purchaseRequestValidations?.[category]
        || emptyPurchaseRequestValidation(
            category
        )
    )
})

const prQuarterLabel = (quarter) =>
    String(quarter || '')
        .trim()
        .toUpperCase()

const prButtonLabel = computed(() =>
    'Validate Purchase Request'
)

const prValidationRows = computed(() => {
    const rows =
        Array.isArray(
            prValidationData.value.rows
        )
            ? [...prValidationData.value.rows]
            : []

    const term =
        String(
            prValidationSearch.value || ''
        )
            .trim()
            .toLowerCase()

    const filtered =
        rows.filter((row) => {
            if (
                prValidationStatusFilter.value !== 'all'
                && row.status !== prValidationStatusFilter.value
            ) {
                return false
            }

            if (!term) {
                return true
            }

            const haystack = [
                row.item,
                row.unit,
                row.quarter,
                row.quantity,
                row.status,
                row.notes,
            ]
                .map(
                    (value) =>
                        String(value ?? '')
                            .toLowerCase()
                )
                .join(' ')

            return haystack.includes(term)
        })

    const priority = {
        not_match: 1,
        not_found: 2,
        match: 3,
    }

    return filtered.sort(
        (a, b) =>
            (priority[a.status] || 99)
            - (priority[b.status] || 99)
    )
})

const prStatusLabel = (status) => {
    return {
        match: 'Match',
        not_match: 'Not Match',
        not_found: 'Not Found',
    }[status] || 'Not Match'
}

const prStatusClass = (status) => {
    if (status === 'match') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700'
    }

    if (status === 'not_found') {
        return 'border-rose-200 bg-rose-50 text-rose-700'
    }

    return 'border-amber-200 bg-amber-50 text-amber-700'
}

const prDisplayNumber = (value) => {
    if (
        value === null
        || value === undefined
        || String(value).trim() === ''
    ) {
        return '—'
    }

    const number =
        Number(value)

    return Number.isFinite(number)
        ? number.toLocaleString()
        : String(value)
}

const prUploadedAt = computed(() => {
    const raw =
        prValidationData.value
            ?.file
            ?.uploaded_at

    if (!raw) {
        return '—'
    }

    const date =
        new Date(raw)

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

const currentPurchaseRequestCategory = () => {
    if (activeTab.value === 'supplies') {
        return 'supplies'
    }

    if (activeTab.value === 'ict') {
        return 'ict'
    }

    if (activeTab.value === 'other') {
        return otherCategoryFilter.value
    }

    return prValidationCategory.value
}

const openPurchaseRequestValidation = () => {
    const category =
        currentPurchaseRequestCategory()

    prValidationReturnTab.value =
        activeTab.value

    prValidationReturnOtherCategory.value =
        otherCategoryFilter.value

    prValidationCategory.value =
        category

    if (
        ['supplies', 'ict'].includes(
            category
        )
    ) {
        prValidationYear.value =
            Number(
                yearFilter.value
            ) || 2026
    }

    prValidationUploadError.value = ''
    prValidationSearch.value = ''
    prValidationStatusFilter.value = 'all'

    activeTab.value =
        'purchase_request'
}

const backFromPurchaseRequestValidation = () => {
    const destination =
        prValidationReturnTab.value

    if (
        ['supplies', 'ict'].includes(
            destination
        )
    ) {
        yearFilter.value =
            Number(
                prValidationYear.value
            ) || 2026
    }

    if (destination === 'other') {
        otherCategoryFilter.value =
            prValidationReturnOtherCategory.value
    }

    activeTab.value =
        ['supplies', 'ict', 'other'].includes(
            destination
        )
            ? destination
            : 'supplies'

    currentPage.value = 1
}

const choosePurchaseRequestFile = () => {
    prValidationUploadError.value = ''

    prValidationFileInput.value?.click()
}

const uploadPurchaseRequestFile = (event) => {
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
        prValidationUploadError.value =
            'Please select an XLSX or CSV Purchase Request file.'

        event.target.value = ''
        return
    }

    const formData =
        new FormData()

    formData.append(
        'validation_category',
        prValidationCategory.value
    )

    if (prValidationRequiresYear.value) {
        formData.append(
            'inventory_year',
            String(
                prValidationYear.value
            )
        )
    }

    formData.append(
        'pr_file',
        file
    )

    prValidationUploading.value = true
    prValidationUploadError.value = ''

    router.post(
        '/dts/inventory/purchase-request/validate',
        formData,
        {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,

            onSuccess: () => {
                activeTab.value =
                    'purchase_request'

                prValidationSearch.value =
                    ''

                prValidationStatusFilter.value =
                    'all'
            },

            onError: (errors) => {
                prValidationUploadError.value =
                    errors?.pr_file
                    || errors?.inventory_year
                    || errors?.validation_category
                    || 'Unable to validate the Purchase Request file.'
            },

            onFinish: () => {
                prValidationUploading.value =
                    false

                if (
                    prValidationFileInput.value
                ) {
                    prValidationFileInput.value.value =
                        ''
                }
            },
        }
    )
}


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


/*
|--------------------------------------------------------------------------
| ACCESSIBILITY PANEL
|--------------------------------------------------------------------------
|
| Inventory-only accessibility controls inspired by the supplied design.
| Preferences are stored in localStorage so they survive refreshes.
|
*/

const ACCESSIBILITY_STORAGE_KEY =
    'dts_inventory_accessibility_v1'

const defaultAccessibilitySettings = {
    textSize: 100,
    highContrast: false,
    grayscale: false,
    highlightLinks: false,
    readableFont: false,
    bigCursor: false,
    readingGuide: false,
}

const accessibilityOpen = ref(false)

const accessibilitySettings = ref({
    ...defaultAccessibilitySettings,
})

const readingGuideY = ref(0)

let accessibilityTextObserver = null
let accessibilityTextApplyFrame = null

const accessibilityTextElements = new Set()

const clampAccessibilityTextSize = (value) => {
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

const accessibilityRootClasses = computed(() => ({
    'a11y-readable-font':
        accessibilitySettings.value.readableFont,

    'a11y-highlight-links':
        accessibilitySettings.value.highlightLinks,

    'a11y-big-cursor':
        accessibilitySettings.value.bigCursor,
}))

const accessibilityVisualStyle = computed(() => {
    const filters = []

    if (accessibilitySettings.value.grayscale) {
        filters.push('grayscale(1)')
    }

    if (accessibilitySettings.value.highContrast) {
        filters.push('contrast(1.35) saturate(1.08)')
    }

    return {
        filter:
            filters.length
                ? filters.join(' ')
                : 'none',
    }
})

const accessibilityOptionButtonClass = (key) => {
    return accessibilitySettings.value[key]
        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-100'
        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'
}

const toggleAccessibilityPanel = () => {
    accessibilityOpen.value =
        !accessibilityOpen.value
}

const closeAccessibilityPanel = () => {
    accessibilityOpen.value = false
}

const toggleAccessibilitySetting = (key) => {
    if (
        !Object.prototype.hasOwnProperty.call(
            accessibilitySettings.value,
            key
        )
        || key === 'textSize'
    ) {
        return
    }

    accessibilitySettings.value[key] =
        !accessibilitySettings.value[key]
}

const decreaseAccessibilityTextSize = () => {
    accessibilitySettings.value.textSize =
        clampAccessibilityTextSize(
            accessibilitySettings.value.textSize - 10
        )
}

const increaseAccessibilityTextSize = () => {
    accessibilitySettings.value.textSize =
        clampAccessibilityTextSize(
            accessibilitySettings.value.textSize + 10
        )
}

const resetAccessibilitySettings = () => {
    accessibilitySettings.value = {
        ...defaultAccessibilitySettings,
    }

    accessibilityOpen.value = true
}

const saveAccessibilitySettings = () => {
    if (typeof window === 'undefined') {
        return
    }

    try {
        window.localStorage.setItem(
            ACCESSIBILITY_STORAGE_KEY,
            JSON.stringify(
                accessibilitySettings.value
            )
        )
    } catch (error) {
        // Accessibility still works for the current page session.
    }
}

const loadAccessibilitySettings = () => {
    if (typeof window === 'undefined') {
        return
    }

    try {
        const raw =
            window.localStorage.getItem(
                ACCESSIBILITY_STORAGE_KEY
            )

        if (!raw) {
            return
        }

        const saved = JSON.parse(raw)

        accessibilitySettings.value = {
            ...defaultAccessibilitySettings,
            ...saved,
            textSize:
                clampAccessibilityTextSize(
                    saved?.textSize
                ),
        }
    } catch (error) {
        accessibilitySettings.value = {
            ...defaultAccessibilitySettings,
        }
    }
}

const restoreAccessibilityElementFont = (element) => {
    if (!(element instanceof HTMLElement)) {
        return
    }

    const originalValue =
        element.dataset.a11yOriginalFontSize ?? ''

    const originalPriority =
        element.dataset.a11yOriginalFontPriority ?? ''

    if (originalValue) {
        element.style.setProperty(
            'font-size',
            originalValue,
            originalPriority
        )
    } else {
        element.style.removeProperty('font-size')
    }

    delete element.dataset.a11yBaseFontSize
    delete element.dataset.a11yOriginalFontSize
    delete element.dataset.a11yOriginalFontPriority

    accessibilityTextElements.delete(element)
}

const restoreAllAccessibilityTextSizes = () => {
    accessibilityTextElements
        .forEach((element) => {
            restoreAccessibilityElementFont(element)
        })

    accessibilityTextElements.clear()
}

const isAccessibilityTextElement = (element) => {
    if (!(element instanceof HTMLElement)) {
        return false
    }

    /*
     * Form controls display text even though they do not have
     * normal text nodes inside them.
     */
    if (
        element.matches(
            'input, textarea, select, option'
        )
    ) {
        return true
    }

    /*
     * Scale an element only when it contains its OWN visible text.
     *
     * This prevents wrapper divs/cards/layout containers from getting
     * a font-size override, which made the previous version feel like
     * the whole interface was zooming.
     */
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
        || !isAccessibilityTextElement(element)
    ) {
        return
    }

    /*
     * Do NOT resize the accessibility controller itself.
     * Only the actual Inventory page content/modals should change.
     */
    if (
        element.closest(
            '.inventory-accessibility-ui'
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
            !Number.isFinite(computedFontSize)
            || computedFontSize <= 0
        ) {
            return
        }

        element.dataset.a11yBaseFontSize =
            String(computedFontSize)

        element.dataset.a11yOriginalFontSize =
            element.style.getPropertyValue(
                'font-size'
            )

        element.dataset.a11yOriginalFontPriority =
            element.style.getPropertyPriority(
                'font-size'
            )

        accessibilityTextElements.add(
            element
        )
    }

    const baseFontSize =
        Number(
            element.dataset.a11yBaseFontSize
        )

    if (!Number.isFinite(baseFontSize)) {
        return
    }

    /*
     * At 100%, restore the element exactly as it was.
     * At another percentage, force the computed base size × scale.
     *
     * This intentionally handles BOTH:
     * - Tailwind rem classes like text-sm / text-2xl
     * - fixed pixel classes like text-[9px] / text-[10px]
     */
    if (scale === 1) {
        const originalValue =
            element.dataset.a11yOriginalFontSize ?? ''

        const originalPriority =
            element.dataset.a11yOriginalFontPriority ?? ''

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
        typeof document === 'undefined'
        || typeof window === 'undefined'
    ) {
        return
    }

    const root =
        document.querySelector(
            '.inventory-accessibility-root'
        )

    if (!root) {
        return
    }

    const scale =
        clampAccessibilityTextSize(
            accessibilitySettings.value.textSize
        ) / 100

    /*
     * Scale ONLY elements that directly render text.
     *
     * Containers/layout wrappers are deliberately ignored, so this
     * changes literal font sizes instead of making the page feel zoomed.
     *
     * Each text element keeps its own original computed size as baseline,
     * including Tailwind classes such as text-sm and text-[9px].
     */
    root.querySelectorAll('*')
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
            accessibilityTextApplyFrame = null
            applyAccessibilityTextSize()
        })
}

const startAccessibilityTextObserver = () => {
    if (
        typeof document === 'undefined'
        || typeof MutationObserver === 'undefined'
    ) {
        return
    }

    const root =
        document.querySelector(
            '.inventory-accessibility-root'
        )

    if (!root) {
        return
    }

    accessibilityTextObserver =
        new MutationObserver(
            (mutations) => {
                const hasAddedContent =
                    mutations.some(
                        (mutation) =>
                            mutation.addedNodes.length > 0
                    )

                if (hasAddedContent) {
                    scheduleAccessibilityTextSize()
                }
            }
        )

    accessibilityTextObserver.observe(
        root,
        {
            childList: true,
            subtree: true,
        }
    )
}

const handleReadingGuidePointer = (event) => {
    if (
        !accessibilitySettings.value.readingGuide
    ) {
        return
    }

    readingGuideY.value =
        Number(event?.clientY || 0)
}

const handleAccessibilityKeydown = (event) => {
    if (event?.key === 'Escape') {
        closeAccessibilityPanel()
    }
}

watch(
    accessibilitySettings,
    () => {
        saveAccessibilitySettings()
        scheduleAccessibilityTextSize()
    },
    {
        deep: true,
    }
)

onMounted(() => {
    loadAccessibilitySettings()

    if (typeof window !== 'undefined') {
        scheduleAccessibilityTextSize()
        startAccessibilityTextObserver()
        readingGuideY.value =
            Math.round(
                window.innerHeight / 2
            )

        window.addEventListener(
            'pointermove',
            handleReadingGuidePointer,
            {
                passive: true,
            }
        )

        window.addEventListener(
            'keydown',
            handleAccessibilityKeydown
        )
    }
})

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener(
            'pointermove',
            handleReadingGuidePointer
        )

        window.removeEventListener(
            'keydown',
            handleAccessibilityKeydown
        )
    }

    if (accessibilityTextObserver) {
        accessibilityTextObserver.disconnect()
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

const showHistoryModal = ref(false)
const historyItem = ref(null)
const inventoryHistories = ref([])
const historyLoading = ref(false)
const historyError = ref('')

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
    remarks: '',
})

const showFullEditModal = ref(false)
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
            entry.opening = 0
            entry.carryover = 0
            entry.added = 0
            entry.current = 0
            entry.released = 0
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

    if (activeTab.value === 'ict') {
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

const ictEditorAssetRows = (assets) => {
    if (!Array.isArray(assets)) {
        return []
    }

    return assets.map((asset) => ({
        description: String(
            asset?.description ?? ''
        ).trim(),
        property_number: String(
            asset?.property_number ?? ''
        ).trim(),
        current_user: String(
            asset?.current_user ?? ''
        ).trim(),
    }))
}

const normalizeIctAssets = (assets) => {
    return ictEditorAssetRows(assets)
        .filter(
            (asset) =>
                asset.description
                || asset.property_number
                || asset.current_user
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
            property_number: '',
            current_user: '',
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
    )
}

const hasIctAssetDetails = (item) =>
    ictAssetDetails(item).length > 0

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
                property_number: '',
                current_user: '',
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

        newItemForm.value.ict_assets =
            syncIctAssetRowsToCount(
                newItemForm.value.ict_assets,
                count
            )
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

        fullEditForm.value.ict_assets =
            syncIctAssetRowsToCount(
                fullEditForm.value.ict_assets,
                count
            )
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

    if (
        activeTab.value
        === 'purchase_request'
    ) {
        return 'Purchase Request Validation'
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

            return (
                matchesYear
                && matchesSearch
                && matchesUnit
                && matchesQuarter
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

const currentReconciliationCategory = computed(() => {
    if (activeTab.value === 'supplies') {
        return 'supplies'
    }

    if (activeTab.value === 'ict') {
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

        if (
            requiresItemCount
            && (
                fullEditForm.value.currently_available === ''
                || fullEditForm.value.currently_available === null
                || fullEditForm.value.currently_available === undefined
                || !Number.isInteger(
                    Number(
                        fullEditForm.value.currently_available
                    )
                )
                || Number(
                    fullEditForm.value.currently_available
                ) < 0
            )
        ) {
            fullEditErrors.value.currently_available =
                'Enter a valid count.'
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
                        : 'Enter a valid count.'
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

    const shouldValidatePropertyDetails =
        (
            isIct
            && !isIctSubscription(unit)
        )
        || (
            isOtherItems
            && isPropertyTrackedOtherCategory(
                category
            )
            && (
                existingPropertyAssets.length > 0
                || editedPropertyAssets.length > 0
            )
        )

    if (shouldValidatePropertyDetails) {
        validateIctAssetRows(
            fullEditForm.value.ict_assets,
            fullEditErrors.value,
            currentValue,
            category
        )
    }

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
        payload.currently_available =
            requiresItemCount
                ? Number(
                    fullEditForm.value.currently_available
                )
                : null
        payload.ict_assets =
            isPropertyTrackedOtherCategory(
                category
            )
                ? normalizeIctAssets(
                    fullEditForm.value.ict_assets
                )
                : []
    } else if (isIct) {
        payload.location = null
        payload.unit = unit
        payload.inventory_year = inventoryYear
        payload.fixed_value = null
        payload.currently_available =
            currentValue
        payload.ict_assets =
            isIctSubscription(unit)
                ? []
                : normalizeIctAssets(
                    fullEditForm.value.ict_assets
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
        remarks: '',
    }

    releaseItemErrors.value = {}
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
        class="inventory-accessibility-root inventory-comfort-theme min-h-screen bg-[#e8eef5]"
        :class="accessibilityRootClasses"
    >
        <main
            class="mx-auto max-w-[1700px] px-4 py-5 sm:px-6 lg:px-8"
            :style="accessibilityVisualStyle"
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
                                    {{
                                        activeTab === 'purchase_request'
                                            ? 'Purchase Request Validation'
                                            : 'Inventory Monitoring'
                                    }}
                                </h1>

                                <p
                                    v-if="activeTab === 'purchase_request'"
                                    class="mt-1 text-xs font-semibold text-slate-500"
                                >
                                    {{ purchaseRequestCategoryLabel(prValidationCategory) }} Purchase Request
                                </p>
                            </div>
                        </div>

                       
                    </div>

                    <div
                        class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:justify-end"
                    >
                        <!-- PURCHASE REQUEST VALIDATION -->
                        <button
                            v-if="['supplies', 'ict', 'other'].includes(activeTab)"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border-2 border-blue-600 bg-blue-600 px-5 text-sm font-black text-white shadow-md shadow-blue-100 transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-100"
                            @click="openPurchaseRequestValidation"
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
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <path d="M14 2v6h6" />
                                <path d="m9 15 2 2 4-4" />
                            </svg>

                            <span>{{ prButtonLabel }}</span>
                        </button>

                        <button
                            v-if="activeTab === 'purchase_request'"
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 text-sm font-black text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-100"
                            @click="backFromPurchaseRequestValidation"
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
                                <path d="m15 18-6-6 6-6" />
                            </svg>

                            <span>
                                Back to {{ purchaseRequestCategoryLabel(prValidationCategory) }}
                            </span>
                        </button>

                        <button
                            v-if="activeTab !== 'reconciliation' && activeTab !== 'purchase_request'"
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
                                && activeTab !== 'reconciliation'
                                && activeTab !== 'purchase_request'
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
                                && activeTab !== 'purchase_request'
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


            <!-- PURCHASE REQUEST VALIDATION -->
            <section
                v-if="activeTab === 'purchase_request'"
                class="mt-4 space-y-4"
            >
                <div
                    class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 border-b border-slate-100 px-5 py-5 sm:px-6 xl:flex-row xl:items-end xl:justify-between"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.14em] text-blue-600"
                            >
                                {{ purchaseRequestCategoryLabel(prValidationCategory) }} Purchase Request
                            </p>

                            <h2
                                class="mt-1 text-lg font-black text-slate-900"
                            >
                                Purchase Request Validation
                            </h2>
                        </div>

                        <div
                            class="grid w-full gap-2 sm:grid-cols-[150px_1fr] xl:w-auto xl:grid-cols-[150px_auto]"
                            :class="!prValidationRequiresYear ? 'sm:grid-cols-1 xl:grid-cols-1' : ''"
                        >
                            <div
                                v-if="prValidationRequiresYear"
                            >
                                <label
                                    class="mb-1.5 block text-[9px] font-black uppercase tracking-[0.10em] text-slate-500"
                                >
                                    Inventory Year
                                </label>

                                <select
                                    v-model="prValidationYear"
                                    class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-xs font-black text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                >
                                    <option
                                        v-for="year in yearOptions"
                                        :key="`pr-year-${year}`"
                                        :value="year"
                                    >
                                        {{ year }}
                                    </option>
                                </select>
                            </div>

                            <div
                                v-if="canManageInventory"
                                class="sm:self-end"
                            >
                                <input
                                    ref="prValidationFileInput"
                                    type="file"
                                    accept=".xlsx,.csv"
                                    class="hidden"
                                    @change="uploadPurchaseRequestFile"
                                />

                                <button
                                    type="button"
                                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 text-xs font-black text-white shadow-sm shadow-blue-100 transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                                    :disabled="prValidationUploading"
                                    @click="choosePurchaseRequestFile"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path d="M12 3v12" />
                                        <path d="m7 8 5-5 5 5" />
                                        <path d="M5 21h14a2 2 0 0 0 2-2v-4" />
                                        <path d="M3 15v4a2 2 0 0 0 2 2" />
                                    </svg>

                                    <span>
                                        {{
                                            prValidationUploading
                                                ? 'Checking...'
                                                : (
                                                    prValidationData.has_file
                                                        ? 'Replace Purchase Request'
                                                        : 'Upload Purchase Request'
                                                )
                                        }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="prValidationUploadError"
                        class="border-b border-rose-100 bg-rose-50 px-5 py-3 text-xs font-bold text-rose-700 sm:px-6"
                    >
                        {{ prValidationUploadError }}
                    </div>

                    <div
                        v-if="prValidationData.error"
                        class="border-b border-amber-100 bg-amber-50 px-5 py-3 text-xs font-bold text-amber-700 sm:px-6"
                    >
                        {{ prValidationData.error }}
                    </div>

                    <div
                        v-if="prValidationData.has_file"
                        class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-4 sm:px-6"
                    >
                        <div>
                            <p
                                class="text-[9px] font-black uppercase tracking-[0.10em] text-slate-400"
                            >
                                PR File
                            </p>
                            <p
                                class="mt-1 break-words text-xs font-black text-slate-800"
                            >
                                {{
                                    prValidationData.file?.original_name
                                    || 'Purchase Request'
                                }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-[9px] font-black uppercase tracking-[0.10em] text-slate-400"
                            >
                                Inventory
                            </p>
                            <p
                                class="mt-1 text-xs font-black text-slate-800"
                            >
                                {{ purchaseRequestCategoryLabel(prValidationCategory) }}
                                <template
                                    v-if="prValidationRequiresYear"
                                >
                                    · {{ prValidationData.context?.inventory_year || prValidationYear }}
                                </template>
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-[9px] font-black uppercase tracking-[0.10em] text-slate-400"
                            >
                                Uploaded By
                            </p>
                            <p
                                class="mt-1 text-xs font-black text-slate-800"
                            >
                                {{ prValidationData.file?.uploaded_by || '—' }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-[9px] font-black uppercase tracking-[0.10em] text-slate-400"
                            >
                                Uploaded
                            </p>
                            <p
                                class="mt-1 text-xs font-black text-slate-800"
                            >
                                {{ prUploadedAt }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!prValidationData.has_file"
                    class="rounded-[1.75rem] border border-dashed border-blue-200 bg-white px-6 py-14 text-center shadow-sm"
                >
                    <div
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600"
                    >
                        <svg
                            class="h-7 w-7"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <path d="M14 2v6h6" />
                            <path d="m9 15 2 2 4-4" />
                        </svg>
                    </div>

                    <h3
                        class="mt-4 text-lg font-black text-slate-900"
                    >
                        Upload the Purchase Request
                    </h3>

                    <p
                        class="mx-auto mt-2 max-w-2xl text-xs font-semibold leading-6 text-slate-500"
                    >
                        Required columns: {{ prValidationRequiredFields }}
                    </p>
                </div>

                <template v-else>
                    <div
                        class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <div
                            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                        >
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400"
                            >
                                PR Items
                            </p>
                            <p
                                class="mt-2 text-2xl font-black tabular-nums text-slate-900"
                            >
                                {{ prValidationData.summary?.total || 0 }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm"
                        >
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.12em] text-emerald-600"
                            >
                                Match
                            </p>
                            <p
                                class="mt-2 text-2xl font-black tabular-nums text-emerald-700"
                            >
                                {{ prValidationData.summary?.match || 0 }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-sm"
                        >
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.12em] text-amber-600"
                            >
                                Not Match
                            </p>
                            <p
                                class="mt-2 text-2xl font-black tabular-nums text-amber-700"
                            >
                                {{ prValidationData.summary?.not_match || 0 }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-rose-200 bg-rose-50/60 p-4 shadow-sm"
                        >
                            <p
                                class="text-[10px] font-black uppercase tracking-[0.12em] text-rose-600"
                            >
                                Not Found
                            </p>
                            <p
                                class="mt-2 text-2xl font-black tabular-nums text-rose-700"
                            >
                                {{ prValidationData.summary?.not_found || 0 }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm"
                    >
                        <div
                            class="border-b border-slate-200 px-5 py-4 sm:px-6"
                        >
                            <div
                                class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
                            >
                                <div>
                                    <h3
                                        class="text-base font-black text-slate-900"
                                    >
                                        Validation Results
                                    </h3>
                                </div>

                                <div
                                    class="grid gap-2 sm:grid-cols-2 xl:w-[520px]"
                                >
                                    <input
                                        v-model="prValidationSearch"
                                        type="text"
                                        placeholder="Search item or notes..."
                                        class="h-10 rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-700 outline-none focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-100"
                                    />

                                    <select
                                        v-model="prValidationStatusFilter"
                                        class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                    >
                                        <option value="all">
                                            All results
                                        </option>
                                        <option value="match">
                                            Match
                                        </option>
                                        <option value="not_match">
                                            Not Match
                                        </option>
                                        <option value="not_found">
                                            Not Found
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="prValidationRows.length"
                            class="overflow-x-auto"
                        >
                            <table
                                class="min-w-[820px] w-full table-fixed"
                            >
                                <thead
                                    class="bg-blue-600 text-white"
                                >
                                    <tr>
                                        <th
                                            class="px-4 py-3 text-left text-[9px] font-black uppercase tracking-[0.1em]"
                                        >
                                            Item
                                        </th>

                                        <th
                                            v-if="prValidationUsesUnit"
                                            class="w-[12%] px-4 py-3 text-center text-[9px] font-black uppercase tracking-[0.1em]"
                                        >
                                            Unit
                                        </th>

                                        <th
                                            v-if="prValidationUsesQuarter"
                                            class="w-[12%] px-4 py-3 text-center text-[9px] font-black uppercase tracking-[0.1em]"
                                        >
                                            Quarter
                                        </th>

                                        <th
                                            class="w-[13%] px-4 py-3 text-center text-[9px] font-black uppercase tracking-[0.1em]"
                                        >
                                            Quantity
                                        </th>

                                        <th
                                            class="w-[14%] px-4 py-3 text-center text-[9px] font-black uppercase tracking-[0.1em]"
                                        >
                                            Result
                                        </th>

                                        <th
                                            class="w-[32%] px-4 py-3 text-left text-[9px] font-black uppercase tracking-[0.1em]"
                                        >
                                            Notes
                                        </th>
                                    </tr>
                                </thead>

                                <tbody
                                    class="divide-y divide-slate-100"
                                >
                                    <tr
                                        v-for="row in prValidationRows"
                                        :key="row.id"
                                        class="align-top transition hover:bg-blue-50/30"
                                    >
                                        <td class="px-4 py-4">
                                            <p
                                                class="break-words text-xs font-black leading-5 text-slate-900"
                                            >
                                                {{ row.item || '—' }}
                                            </p>
                                        </td>

                                        <td
                                            v-if="prValidationUsesUnit"
                                            class="px-4 py-4 text-center text-xs font-black text-slate-800"
                                        >
                                            {{ row.unit || '—' }}
                                        </td>

                                        <td
                                            v-if="prValidationUsesQuarter"
                                            class="px-4 py-4 text-center text-xs font-black text-blue-700"
                                        >
                                            {{ prQuarterLabel(row.quarter) || '—' }}
                                        </td>

                                        <td
                                            class="px-4 py-4 text-center text-sm font-black tabular-nums text-slate-900"
                                        >
                                            {{ prDisplayNumber(row.quantity) }}
                                        </td>

                                        <td
                                            class="px-4 py-4 text-center"
                                        >
                                            <span
                                                class="inline-flex rounded-full border px-2.5 py-1.5 text-[10px] font-black"
                                                :class="prStatusClass(row.status)"
                                            >
                                                {{ prStatusLabel(row.status) }}
                                            </span>
                                        </td>

                                        <td class="px-4 py-4">
                                            <p
                                                class="text-[11px] font-semibold leading-5"
                                                :class="
                                                    row.status === 'match'
                                                        ? 'text-emerald-700'
                                                        : row.status === 'not_found'
                                                            ? 'text-rose-700'
                                                            : 'text-amber-700'
                                                "
                                            >
                                                {{ row.notes || '—' }}
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-else
                            class="px-6 py-12 text-center"
                        >
                            <p
                                class="text-sm font-black text-slate-700"
                            >
                                No PR rows match the current filters.
                            </p>
                        </div>
                    </div>
                </template>
            </section>


            <!-- LEDGER WORKSPACE -->
            <section
                v-if="activeTab !== 'reconciliation' && activeTab !== 'purchase_request'"
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
                            </div>
                           
                        </div>

                        <div
                            class="grid w-full grid-cols-1 gap-2 sm:grid-cols-2"
                            :class="
                                activeTab === 'other'
                                    ? 'xl:w-[520px] xl:grid-cols-1'
                                    : activeTab === 'supplies'
                                        ? 'xl:w-[1080px] xl:grid-cols-[minmax(260px,1fr)_110px_140px_120px_150px]'
                                        : 'xl:w-[900px] xl:grid-cols-[minmax(260px,1fr)_110px_140px_120px]'
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

                
                <!-- ICT TABLE -->
                <div
                    v-if="activeTab === 'ict'"
                    class="hidden overflow-hidden lg:block"
                >
                    <table class="inventory-ledger-table w-full table-fixed">
                        <thead class="bg-blue-500 text-white">
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
                                    :role="hasIctAssetDetails(item) ? 'button' : undefined"
                                    :tabindex="hasIctAssetDetails(item) ? 0 : -1"
                                    :aria-expanded="hasIctAssetDetails(item) ? isIctExpanded(item) : undefined"
                                    :title="hasIctAssetDetails(item) ? 'Click to view Property Details' : undefined"
                                    class="bg-white transition"
                                    :class="
                                        hasIctAssetDetails(item)
                                            ? (
                                                isIctExpanded(item)
                                                    ? 'cursor-pointer bg-blue-50/70 hover:bg-blue-50'
                                                    : 'cursor-pointer hover:bg-blue-50/55'
                                            )
                                            : 'cursor-default hover:bg-blue-50/35'
                                    "
                                    @click="
                                        hasIctAssetDetails(item)
                                        && toggleIctAssetDetails(item)
                                    "
                                    @keydown.enter.prevent="
                                        hasIctAssetDetails(item)
                                        && toggleIctAssetDetails(item)
                                    "
                                    @keydown.space.prevent="
                                        hasIctAssetDetails(item)
                                        && toggleIctAssetDetails(item)
                                    "
                                >
                                    <td class="px-4 py-4 align-middle">
                                        <div class="flex items-center gap-2">
                                            <p class="min-w-0 flex-1 break-words text-xs font-black leading-5 text-blue-950">
                                                {{ item.item || '—' }}
                                            </p>

                                            <svg
                                                v-if="hasIctAssetDetails(item)"
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
                                    v-if="hasIctAssetDetails(item) && isIctExpanded(item)"
                                    class="bg-slate-50/80"
                                >
                                    <td
                                        colspan="5"
                                        class="px-4 pb-4 pt-1"
                                    >
                                        <div class="overflow-hidden rounded-xl border border-blue-100 bg-white">
                                            <div class="grid grid-cols-3 border-b border-blue-700 bg-blue-600 px-4 py-2.5 text-[9px] font-black uppercase tracking-[0.09em] text-white">
                                                <span>Description</span>
                                                <span>Property Number</span>
                                                <span>Current User</span>
                                            </div>

                                            <div
                                                v-for="(asset, assetIndex) in ictAssetDetails(item)"
                                                :key="`ict-asset-row-${item.id}-${assetIndex}`"
                                                class="grid grid-cols-3 gap-4 border-b border-blue-100 px-4 py-3 last:border-b-0"
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
                                            <div class="grid grid-cols-3 border-b border-blue-700 bg-blue-600 px-4 py-2.5 text-[9px] font-black uppercase tracking-[0.09em] text-white">
                                                <span>Description</span>
                                                <span>Property Number</span>
                                                <span>Current User</span>
                                            </div>

                                            <div
                                                v-for="(asset, assetIndex) in ictAssetDetails(item)"
                                                :key="`other-asset-row-${item.id}-${assetIndex}`"
                                                class="grid grid-cols-3 gap-4 border-b border-blue-100 px-4 py-3 last:border-b-0"
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
                                <div class="grid grid-cols-3 border-b border-blue-700 bg-blue-600 px-3 py-2.5 text-[8px] font-black uppercase tracking-[0.09em] text-white">
                                    <span>Description</span>
                                    <span>Property Number</span>
                                    <span>Current User</span>
                                </div>

                                <div
                                    v-for="(asset, assetIndex) in ictAssetDetails(item)"
                                    :key="`mobile-other-asset-${item.id}-${assetIndex}`"
                                    class="grid grid-cols-3 gap-3 border-b border-blue-100 px-3 py-2.5 last:border-b-0"
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
                                                ? 'Each ICT unit requires Description and Property Number. Current User is optional.'
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
                                        (
                                            activeTab === 'ict'
                                            || (
                                                activeTab === 'other'
                                                && addOtherCategoryIsAssetTracked
                                            )
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
                                            class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500"
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
                                                    : 'border-slate-200 focus:border-blue-400'
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
                                            class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500"
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
                                                    : 'border-slate-200 focus:border-blue-400'
                                            "
                                        />

                                        <p
                                            v-if="addItemErrors[`ict_assets.${assetIndex}.property_number`]"
                                            class="mt-1 text-[10px] font-bold text-rose-600"
                                        >
                                            {{ addItemErrors[`ict_assets.${assetIndex}.property_number`] }}
                                        </p>
                                    </div>

                                    <div>
                                        <label
                                            class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500"
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
                                            class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                        />
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
                                </div>
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
                        class="sm:col-span-2">
                        <label class="mb-2 block text-sm font-black text-slate-800">Count</label>
                        <input
                            v-model.number="fullEditForm.currently_available"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="Enter number of items"
                            class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-black tabular-nums text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100" />
                        <p
                            v-if="fullEditErrors.currently_available"
                            class="mt-2 text-xs font-bold text-rose-600">
                            {{ fullEditErrors.currently_available }}
                        </p>
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
                    >
                        <label class="mb-2 block text-sm font-black text-slate-800">
                            {{
                                isIctMonthBased(fullEditForm.unit)
                                    ? 'Subscription Duration (Month/s)'
                                    : isIctYearBased(fullEditForm.unit)
                                        ? 'Subscription Duration (Year/s)'
                                        : 'Count'
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
                    </div>

                    <div
                        v-if="
                            (
                                fullEditForm.category === 'ict'
                                && !isIctSubscription(fullEditForm.unit)
                            )
                            || isPropertyTrackedOtherCategory(
                                fullEditForm.category
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
                                        fullEditForm.category === 'ict'
                                            ? 'Each ICT unit requires Description and Property Number. Current User is optional.'
                                            : (
                                                isPropertyTrackedOtherCategory(
                                                    fullEditForm.category
                                                )
                                                    ? 'Each Furniture/Fixtures unit requires Description and Property Number. Current User is optional.'
                                                    : 'Property rows automatically match the Count. Property Number is required; Current User is optional.'
                                            )
                                    }}
                                </p>
                            </div>

                            <span
                                class="rounded-xl border border-blue-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-[0.08em] text-blue-700"
                            >
                                {{
                                    ictEquipmentCount(
                                        fullEditForm.currently_available
                                    )
                                }}
                                Property
                                {{
                                    ictEquipmentCount(
                                        fullEditForm.currently_available
                                    ) === 1
                                        ? 'Row'
                                        : 'Rows'
                                }}
                            </span>
                        </div>

                        <p
                            v-if="fullEditErrors.ict_assets"
                            class="mt-3 text-xs font-bold text-rose-600"
                        >
                            {{ fullEditErrors.ict_assets }}
                        </p>

                        <div
                            v-if="fullEditForm.ict_assets.length"
                            class="mt-4 space-y-3"
                        >
                            <div
                                v-for="(asset, assetIndex) in fullEditForm.ict_assets"
                                :key="`edit-ict-asset-${assetIndex}`"
                                class="grid gap-3 rounded-xl border border-slate-200 bg-white p-3"
                                :class="
                                    (
                                        fullEditForm.category === 'ict'
                                        || isPropertyTrackedOtherCategory(
                                            fullEditForm.category
                                        )
                                    )
                                        ? 'sm:grid-cols-3'
                                        : 'sm:grid-cols-2'
                                "
                            >
                                <div
                                    v-if="
                                        fullEditForm.category === 'ict'
                                        || isPropertyTrackedOtherCategory(
                                            fullEditForm.category
                                        )
                                    "
                                >
                                    <label
                                        class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500"
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
                                            fullEditErrors[`ict_assets.${assetIndex}.description`]
                                                ? 'border-rose-400'
                                                : 'border-slate-200 focus:border-blue-400'
                                        "
                                    />

                                    <p
                                        v-if="fullEditErrors[`ict_assets.${assetIndex}.description`]"
                                        class="mt-1 text-[10px] font-bold text-rose-600"
                                    >
                                        {{ fullEditErrors[`ict_assets.${assetIndex}.description`] }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500"
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
                                            fullEditErrors[`ict_assets.${assetIndex}.property_number`]
                                                ? 'border-rose-400'
                                                : 'border-slate-200 focus:border-blue-400'
                                        "
                                    />

                                    <p
                                        v-if="fullEditErrors[`ict_assets.${assetIndex}.property_number`]"
                                        class="mt-1 text-[10px] font-bold text-rose-600"
                                    >
                                        {{ fullEditErrors[`ict_assets.${assetIndex}.property_number`] }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-[10px] font-black uppercase tracking-[0.08em] text-slate-500"
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
                                        class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-800 outline-none focus:border-blue-400 focus:ring-4 focus:ring-blue-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="mt-4 rounded-xl border border-dashed border-blue-200 bg-white/70 px-4 py-5 text-center text-xs font-semibold text-slate-400"
                        >
                            Set the Count above to generate the Property Detail rows.
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
                                    && !String(
                                        releaseItemForm.releasePropertyNumber
                                        || ''
                                    ).trim()
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
                            class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] font-semibold text-slate-500">
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


        <!-- ACCESSIBILITY READING GUIDE -->
        <div
            v-if="accessibilitySettings.readingGuide"
            class="inventory-accessibility-ui pointer-events-none fixed left-0 right-0 z-[75] h-12 border-y-2 border-indigo-500/35 bg-indigo-200/20 shadow-[0_0_20px_rgba(79,70,229,0.10)]"
            :style="{
                top: `${Math.max(
                    0,
                    readingGuideY - 24
                )}px`,
            }"
            aria-hidden="true"
        ></div>

        <!-- ACCESSIBILITY PANEL -->
        <div
            v-if="accessibilityOpen"
            class="inventory-accessibility-ui fixed bottom-24 right-4 z-[90] w-[calc(100vw-2rem)] max-w-[455px] overflow-hidden rounded-[1.8rem] border border-slate-200 bg-white shadow-[0_26px_80px_rgba(15,23,42,0.25)] sm:right-6"
            role="dialog"
            aria-modal="false"
            aria-label="Accessibility settings"
        >
            <!-- HEADER -->
            <div
                class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5"
            >
                <div class="flex min-w-0 items-center gap-4">
                    <div
                        class="flex h-13 w-13 shrink-0 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-600"
                    >
                        <svg
                            class="h-8 w-8"
                            viewBox="0 0 64 64"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <circle cx="19" cy="16" r="7" fill="currentColor" />
                            <path
                                d="M14 28C14 25.7909 15.7909 24 18 24H27C31.4183 24 35 27.5817 35 32V46L30 42V34C30 32.8954 29.1046 32 28 32H24V50L14 43V28Z"
                                fill="currentColor"
                            />
                            <path
                                d="M39 24L51 20V38L39 42C37.3431 42.5523 35.5523 42.1046 34.3431 40.8284L28 34L34.3431 28.1716C35.5523 26.8954 37.3431 26.4477 39 27L51 31"
                                fill="currentColor"
                            />
                            <path
                                d="M39 24L51 20V38"
                                stroke="white"
                                stroke-width="2.5"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </div>

                    <div class="min-w-0">
                        <h2
                            class="text-xl font-black tracking-tight text-slate-900"
                        >
                            Accessibility
                        </h2>

                        <p
                            class="mt-0.5 text-sm font-medium text-slate-400"
                        >
                            Adjust the page to your needs
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-xl font-black text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close accessibility settings"
                    @click="closeAccessibilityPanel"
                >
                    ×
                </button>
            </div>

            <div
                class="max-h-[calc(100dvh-9rem)] overflow-y-auto px-6 py-5"
            >
                <!-- TEXT SIZE ONLY -->
                <section>
                    <p
                        class="text-xs font-black uppercase tracking-[0.08em] text-slate-400"
                    >
                        Text Size
                    </p>

                    <div
                        class="mt-4 grid grid-cols-[60px_1fr_60px] gap-3"
                    >
                        <button
                            type="button"
                            :disabled="
                                accessibilitySettings.textSize <= 80
                            "
                            class="flex h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white text-2xl font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Decrease text size"
                            @click="decreaseAccessibilityTextSize"
                        >
                            −
                        </button>

                        <div
                            class="flex h-14 items-center justify-center rounded-2xl border border-slate-100 bg-slate-50 text-lg font-black tabular-nums text-slate-900"
                        >
                            {{
                                accessibilitySettings.textSize
                            }}%
                        </div>

                        <button
                            type="button"
                            :disabled="
                                accessibilitySettings.textSize >= 200
                            "
                            class="flex h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white text-2xl font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                            aria-label="Increase text size"
                            @click="increaseAccessibilityTextSize"
                        >
                            +
                        </button>
                    </div>

                    <p
                        class="mt-4 text-sm font-semibold leading-6 text-slate-500"
                    >
                        Adjust the text size for the Inventory content only.
                    </p>
                </section>

                <!-- RESET TEXT SIZE -->
                <button
                    type="button"
                    class="mt-6 flex h-14 w-full items-center justify-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 text-sm font-black text-rose-600 transition hover:bg-rose-100"
                    @click="resetAccessibilitySettings"
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
                        <path d="M3 12a9 9 0 1 0 3-6.7" />
                        <path d="M3 4v6h6" />
                    </svg>

                    Reset text size
                </button>
            </div>
        </div>

        <!-- FLOATING ACCESSIBILITY BUTTON -->
        <button
            type="button"
            class="inventory-accessibility-ui fixed bottom-5 right-5 z-[85] flex h-16 w-16 items-center justify-center rounded-full bg-indigo-600 text-white shadow-[0_16px_35px_rgba(79,70,229,0.38)] transition hover:-translate-y-0.5 hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200 sm:bottom-6 sm:right-6"
            :aria-expanded="accessibilityOpen"
            aria-label="Open accessibility settings"
            @click="toggleAccessibilityPanel"
        >
            <svg
                class="h-9 w-9"
                viewBox="0 0 64 64"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <circle cx="19" cy="16" r="7" fill="currentColor" />
                <path
                    d="M14 28C14 25.7909 15.7909 24 18 24H27C31.4183 24 35 27.5817 35 32V46L30 42V34C30 32.8954 29.1046 32 28 32H24V50L14 43V28Z"
                    fill="currentColor"
                />
                <path
                    d="M39 24L51 20V38L39 42C37.3431 42.5523 35.5523 42.1046 34.3431 40.8284L28 34L34.3431 28.1716C35.5523 26.8954 37.3431 26.4477 39 27L51 31"
                    fill="currentColor"
                />
                <path
                    d="M39 24L51 20V38"
                    stroke="white"
                    stroke-width="2.5"
                    stroke-linejoin="round"
                />
            </svg>
        </button>


    </div>
</template>

<style>
.inventory-accessibility-root {
    zoom: 1 !important;
    transform: none;
    -webkit-text-size-adjust: 100%;
    text-size-adjust: 100%;
}

.inventory-accessibility-root.a11y-readable-font,
.inventory-accessibility-root.a11y-readable-font * {
    font-family:
        Arial,
        Verdana,
        Helvetica,
        sans-serif !important;
}

.inventory-accessibility-root.a11y-highlight-links a {
    text-decoration: underline !important;
    text-decoration-thickness: 3px !important;
    text-underline-offset: 3px !important;
    box-shadow:
        0 0 0 2px rgba(245, 158, 11, 0.45);
}

.inventory-accessibility-root.a11y-big-cursor,
.inventory-accessibility-root.a11y-big-cursor * {
    cursor:
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='36' height='36' viewBox='0 0 36 36'%3E%3Cpath d='M3 2l24 17-9 2 6 11-5 2-6-11-7 7z' fill='%230f172a' stroke='%23ffffff' stroke-width='2' stroke-linejoin='round'/%3E%3C/svg%3E")
        3 2,
        auto !important;
}

@media (max-width: 640px) {
    .inventory-accessibility-root.a11y-big-cursor,
    .inventory-accessibility-root.a11y-big-cursor * {
        cursor: auto !important;
    }
}


/*
|--------------------------------------------------------------------------
| Inventory Comfort Theme
|--------------------------------------------------------------------------
| Softer blue-gray surfaces reduce glare while keeping strong contrast.
| This intentionally avoids a mostly-white interface, especially for
| users who may find bright screens tiring to read.
*/
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

/* Softer expanded rows without the old vertical blue stripe. */
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

/* Property-detail blocks stay distinct without a thick blue left line. */
.inventory-comfort-theme .inventory-ledger-table .border-2.border-blue-200 {
    border-color: #adc4dc !important;
    background-color: #f3f7fb !important;
    box-shadow:
        0 8px 20px rgba(51, 65, 85, 0.08);
}

/* Soften blue pills while retaining readable contrast. */
.inventory-comfort-theme .bg-blue-50 {
    background-color: #e7f0fa !important;
}

.inventory-comfort-theme .border-blue-100 {
    border-color: #c5d9ec !important;
}

.inventory-comfort-theme .border-blue-200 {
    border-color: #a9c7e4 !important;
}

/* Accessibility UI remains visually independent and crisp. */
.inventory-comfort-theme .inventory-accessibility-ui.bg-white {
    background-color: #ffffff !important;
}



/* Keep non-interactive rows/cards visually neutral. Only controls look clickable. */
.inventory-comfort-theme .cursor-default {
    cursor: default !important;
}

.inventory-comfort-theme button:not(:disabled) {
    cursor: pointer;
}

</style>
