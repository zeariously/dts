<script setup>
import {
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue'

const props = defineProps({
    logoSrc: {
        type: String,
        default: '/images/dost-logo.png',
    },
})

const STORAGE_KEY =
    'dts_global_accessibility_v1'

const LEGACY_STORAGE_KEY =
    'dts_inventory_accessibility_v1'

const rootRef = ref(null)
const accessibilityOpen = ref(false)
const textSize = ref(100)

let textObserver = null
let textApplyFrame = null

const textElements = new Set()

const clampTextSize = (value) => {
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

const toggleAccessibilityPanel = () => {
    accessibilityOpen.value =
        !accessibilityOpen.value
}

const closeAccessibilityPanel = () => {
    accessibilityOpen.value = false
}

const decreaseTextSize = () => {
    textSize.value =
        clampTextSize(
            textSize.value - 10
        )
}

const increaseTextSize = () => {
    textSize.value =
        clampTextSize(
            textSize.value + 10
        )
}

const resetTextSize = () => {
    textSize.value = 100
    accessibilityOpen.value = true
}

const saveSettings = () => {
    if (typeof window === 'undefined') {
        return
    }

    try {
        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                textSize: textSize.value,
            })
        )
    } catch (error) {
        // Accessibility remains available for this page session.
    }
}

const loadSettings = () => {
    if (typeof window === 'undefined') {
        return
    }

    try {
        /*
         * Use the new global key first.
         * If the user previously changed the Inventory text size,
         * migrate that value automatically.
         */
        const raw =
            window.localStorage.getItem(
                STORAGE_KEY
            )
            || window.localStorage.getItem(
                LEGACY_STORAGE_KEY
            )

        if (!raw) {
            return
        }

        const saved = JSON.parse(raw)

        textSize.value =
            clampTextSize(
                saved?.textSize
            )
    } catch (error) {
        textSize.value = 100
    }
}

const restoreElementFont = (element) => {
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
        element.style.removeProperty(
            'font-size'
        )
    }

    delete element.dataset.a11yBaseFontSize
    delete element.dataset.a11yOriginalFontSize
    delete element.dataset.a11yOriginalFontPriority

    textElements.delete(element)
}

const restoreAllTextSizes = () => {
    textElements.forEach((element) => {
        restoreElementFont(element)
    })

    textElements.clear()
}

const isTextElement = (element) => {
    if (!(element instanceof HTMLElement)) {
        return false
    }

    /*
     * Do not resize the accessibility panel/button itself.
     */
    if (
        element.closest(
            '.dts-accessibility-ui'
        )
    ) {
        return false
    }

    /*
     * Form controls render text without normal child text nodes.
     */
    if (
        element.matches(
            'input, textarea, select, option'
        )
    ) {
        return true
    }

    /*
     * Scale only elements that contain their own visible text.
     * This avoids scaling layout wrappers/cards.
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

const scaleElementFont = (
    element,
    scale
) => {
    if (
        !(element instanceof HTMLElement)
        || !isTextElement(element)
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

        textElements.add(element)
    }

    const baseFontSize =
        Number(
            element.dataset.a11yBaseFontSize
        )

    if (!Number.isFinite(baseFontSize)) {
        return
    }

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

const applyTextSize = () => {
    if (
        typeof window === 'undefined'
        || !rootRef.value
    ) {
        return
    }

    const scale =
        clampTextSize(
            textSize.value
        ) / 100

    rootRef.value
        .querySelectorAll('*')
        .forEach((element) => {
            scaleElementFont(
                element,
                scale
            )
        })
}

const scheduleTextSize = () => {
    if (typeof window === 'undefined') {
        return
    }

    if (textApplyFrame) {
        window.cancelAnimationFrame(
            textApplyFrame
        )
    }

    textApplyFrame =
        window.requestAnimationFrame(() => {
            textApplyFrame = null
            applyTextSize()
        })
}

const startTextObserver = () => {
    if (
        typeof MutationObserver === 'undefined'
        || !rootRef.value
    ) {
        return
    }

    textObserver =
        new MutationObserver(
            (mutations) => {
                const hasAddedContent =
                    mutations.some(
                        (mutation) =>
                            mutation.addedNodes.length > 0
                    )

                if (hasAddedContent) {
                    scheduleTextSize()
                }
            }
        )

    textObserver.observe(
        rootRef.value,
        {
            childList: true,
            subtree: true,
        }
    )
}

const handleKeydown = (event) => {
    if (event?.key === 'Escape') {
        closeAccessibilityPanel()
    }
}

watch(
    textSize,
    () => {
        saveSettings()
        scheduleTextSize()
    }
)

onMounted(async () => {
    loadSettings()

    await nextTick()

    scheduleTextSize()
    startTextObserver()

    if (typeof window !== 'undefined') {
        window.addEventListener(
            'keydown',
            handleKeydown
        )
    }
})

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener(
            'keydown',
            handleKeydown
        )
    }

    if (textObserver) {
        textObserver.disconnect()
        textObserver = null
    }

    if (
        typeof window !== 'undefined'
        && textApplyFrame
    ) {
        window.cancelAnimationFrame(
            textApplyFrame
        )

        textApplyFrame = null
    }

    restoreAllTextSizes()
})
</script>

<template>
    <!--
        Everything placed inside this component is covered by
        the global accessibility text-size control.
    -->
    <div
        ref="rootRef"
        class="dts-accessibility-root"
    >
        <slot />
    </div>

    <!-- ACCESSIBILITY PANEL -->
    <div
        v-if="accessibilityOpen"
        class="dts-accessibility-ui fixed bottom-24 right-4 z-[9999] w-[calc(100vw-2rem)] max-w-[455px] overflow-hidden rounded-[1.8rem] border border-slate-200 bg-white shadow-[0_26px_80px_rgba(15,23,42,0.25)] sm:right-6"
        role="dialog"
        aria-modal="false"
        aria-label="Accessibility settings"
    >
        <!-- HEADER -->
        <div
            class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5"
        >
            <div
                class="flex min-w-0 items-center gap-4"
            >
                <!-- DOST LOGO -->
                <div
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-blue-100 bg-white p-1.5 shadow-sm"
                >
                    <img
                        :src="props.logoSrc"
                        alt="DOST Logo"
                        class="h-full w-full object-contain"
                    />
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
                        Adjust the system to your needs
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
                        :disabled="textSize <= 80"
                        class="flex h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white text-2xl font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Decrease text size"
                        @click="decreaseTextSize"
                    >
                        −
                    </button>

                    <div
                        class="flex h-14 items-center justify-center rounded-2xl border border-slate-100 bg-slate-50 text-lg font-black tabular-nums text-slate-900"
                    >
                        {{ textSize }}%
                    </div>

                    <button
                        type="button"
                        :disabled="textSize >= 200"
                        class="flex h-14 items-center justify-center rounded-2xl border border-slate-200 bg-white text-2xl font-black text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                        aria-label="Increase text size"
                        @click="increaseTextSize"
                    >
                        +
                    </button>
                </div>

                <p
                    class="mt-4 text-sm font-semibold leading-6 text-slate-500"
                >
                    Adjust the text size across all DTS pages.
                </p>
            </section>

            <button
                type="button"
                class="mt-6 flex h-14 w-full items-center justify-center gap-2 rounded-2xl border border-rose-200 bg-rose-50 text-sm font-black text-rose-600 transition hover:bg-rose-100"
                @click="resetTextSize"
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

    <!-- FLOATING DOST BUTTON -->
    <button
        type="button"
        class="dts-accessibility-ui fixed bottom-5 right-5 z-[9998] flex h-[68px] w-[68px] items-center justify-center rounded-full border-2 border-blue-200 bg-white p-1.5 shadow-[0_16px_35px_rgba(15,74,148,0.30)] transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-[0_18px_40px_rgba(15,74,148,0.38)] focus:outline-none focus:ring-4 focus:ring-blue-200 sm:bottom-6 sm:right-6"
        :aria-expanded="accessibilityOpen"
        aria-label="Open accessibility settings"
        title="Accessibility"
        @click="toggleAccessibilityPanel"
    >
        <img
            :src="props.logoSrc"
            alt=""
            class="h-full w-full rounded-full object-contain"
            aria-hidden="true"
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
