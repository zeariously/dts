<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Search...',
    },
    idKeys: {
        type: Array,
        default: () => ['ID', 'id'],
    },
    labelKeys: {
        type: Array,
        default: () => ['name', 'title', 'description'],
    },
})

const emit = defineEmits(['update:modelValue'])

const root = ref(null)
const searchText = ref('')
const isOpen = ref(false)

const getOptionId = (option) => {
    for (const key of props.idKeys) {
        if (option?.[key] !== undefined && option?.[key] !== null) {
            return option[key]
        }
    }

    return ''
}

const getOptionLabel = (option) => {
    for (const key of props.labelKeys) {
        const value = option?.[key]

        if (value !== undefined && value !== null && String(value).trim() !== '') {
            return value
        }
    }

    return '-'
}

const selectedOption = computed(() => {
    return props.options.find((option) => {
        return String(getOptionId(option)) === String(props.modelValue)
    })
})

watch(
    () => props.modelValue,
    () => {
        searchText.value = selectedOption.value
            ? getOptionLabel(selectedOption.value)
            : ''
    },
    { immediate: true }
)

const filteredOptions = computed(() => {
    const keyword = String(searchText.value || '').toLowerCase().trim()

    if (!keyword) {
        return props.options.slice(0, 100)
    }

    return props.options
        .filter((option) => {
            return String(getOptionLabel(option)).toLowerCase().includes(keyword)
        })
        .slice(0, 100)
})

const openDropdown = () => {
    isOpen.value = true
}

const selectOption = (option) => {
    emit('update:modelValue', getOptionId(option))
    searchText.value = getOptionLabel(option)
    isOpen.value = false
}

const clearSelection = () => {
    emit('update:modelValue', '')
    searchText.value = ''
    isOpen.value = true
}

const handleClickOutside = (event) => {
    if (!root.value) return

    if (!root.value.contains(event.target)) {
        isOpen.value = false

        if (selectedOption.value) {
            searchText.value = getOptionLabel(selectedOption.value)
        } else {
            searchText.value = ''
        }
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div ref="root" class="relative block w-full min-w-0">
        <input
            v-model="searchText"
            type="text"
            class="block w-full min-w-0 rounded-xl border border-slate-300 bg-white px-4 py-3 pr-10 text-sm text-slate-700 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            :placeholder="placeholder"
            @focus="openDropdown"
            @input="openDropdown"
        />

        <button
            v-if="searchText"
            type="button"
            class="absolute inset-y-0 right-3 flex items-center text-sm font-bold text-slate-400 hover:text-slate-700"
            @click.stop="clearSelection"
        >
            ×
        </button>

        <div
            v-if="isOpen"
            class="absolute left-0 right-0 z-[9999] mt-2 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg"
        >
            <button
                v-for="option in filteredOptions"
                :key="getOptionId(option)"
                type="button"
                class="block w-full px-4 py-3 text-left text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700"
                @click="selectOption(option)"
            >
                {{ getOptionLabel(option) }}
            </button>

            <div
                v-if="filteredOptions.length === 0"
                class="px-4 py-3 text-sm text-slate-500"
            >
                No results found.
            </div>
        </div>
    </div>
</template>