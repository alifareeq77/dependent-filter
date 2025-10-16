<template>
    <div v-show="loading || !filter.hideWhenEmpty || availableOptions.length > 0">
        <h3 class="text-sm uppercase tracking-wide text-80 bg-30 p-3">{{ filter.name }}</h3>

        <div class="p-2">
            <select
                    :dusk="`${filter.name}-filter-select`"
                    class="block w-full form-control-sm form-select"
                    :value="value"
                    @change="handleChange($event.target.value)"
            >
                <option value="">&mdash;</option>
                <option 
                    v-for="option in availableOptions" 
                    :key="option.value"
                    :value="option.value"
                    :selected="option.value == value"
                >
                    {{ option.label || option.name || option.value }}
                </option>
            </select>
        </div>
    </div>
</template>

<script>
    import { ref, computed, watch, watchEffect, onMounted, nextTick } from 'vue'
    import { useStore } from 'vuex'
    import { filter as lodashFilter, every, intersection, castArray } from 'lodash'

    export default {
        props: {
            resourceName: {
                type: String,
                required: true,
            },
            lens: String,
            filterKey: {
                type: String,
                required: true,
            },
        },

        setup(props, { emit }) {
            const store = useStore()
            const log = (...args) => console.log('[DependentFilter]', props.filterKey, ...args)
            const options = ref([])
            const loading = ref(false)

            const getFilter = () => {
                return store.getters[`${props.resourceName}/getFilter`](props.filterKey)
            }

            const currentFilter = computed(() => getFilter())
            const value = computed(() => currentFilter.value.currentValue)
            log('init', { resourceName: props.resourceName, lens: props.lens, filterKey: props.filterKey })

            const optionValue = (option) => {
                return option.label || option.name || option.value
            }

            const handleChange = (value) => {
                log('handleChange ->', value)
                store.commit(`${props.resourceName}/updateFilterState`, {
                    filterClass: props.filterKey,
                    value: value,
                })

                emit('change')
            }

            const availableOptions = computed(() => {
                const filtered = lodashFilter(options.value, option => {
                    return !option.hasOwnProperty('depends') || every(option.depends, (values, filterName) => {
                        const filterObj = store.getters[`${props.resourceName}/getFilter`](filterName)
                        if (!filterObj) return true
                        return intersection(
                            castArray(filterObj.currentValue).map(String),
                            castArray(values).map(String)
                        ).length > 0
                    })
                })
                log('availableOptions computed', { count: filtered.length, options: filtered })
                return filtered
            })

            // Ensure selected value remains valid given available options
            watchEffect(() => {
                const current = value.value
                const opts = availableOptions.value
                if (!loading.value && current !== '' && !opts.some(o => o.value == current)) {
                    log('resetting invalid selection', { current, optsCount: opts.length })
                    nextTick(() => handleChange(''))
                }
            })

            const fetchOptions = async (filters) => {
                const lens = props.lens ? `/lens/${props.lens}` : ''
                try {
                    log('fetchOptions -> request', { resource: props.resourceName, lens: props.lens, filters })
                    const { data } = await Nova.request().get(`/nova-api/${props.resourceName}${lens}/filters/options`, {
                        params: {
                            filters: btoa(JSON.stringify(filters)),
                            filter: props.filterKey,
                        },
                    })
                    log('fetchOptions <- response', data)
                    options.value = data
                } catch (error) {
                    console.error('[DependentFilter]', props.filterKey, 'Error fetching filter options:', error)
                } finally {
                    loading.value = false
                    log('fetchOptions done. loading =', loading.value)
                }
            }

            // React to changes in dependent filters' values
            watch(
                () => currentFilter.value.dependentOf.map((name) => {
                    const f = store.getters[`${props.resourceName}/getFilter`](name)
                    return f ? f.currentValue : ''
                }),
                (values) => {
                    log('dependent values changed', values)
                    const dependentFilters = currentFilter.value.dependentOf.reduce((r, name, idx) => {
                        r[name] = values[idx]
                        return r
                    }, {})

                    loading.value = true
                    log('fetching with dependentFilters', dependentFilters)
                    fetchOptions(dependentFilters)
                },
                { immediate: true }
            )

            onMounted(() => {
                log('mounted; filter meta', {
                    name: currentFilter.value.name,
                    dependentOf: currentFilter.value.dependentOf,
                    hideWhenEmpty: currentFilter.value.hideWhenEmpty,
                })
                options.value = currentFilter.value.options
                log('initial options from server (if any)', options.value)
            })

            watch(options, (val) => log('options updated', val))
            watch(loading, (val) => log('loading =', val))

            return {
                options,
                loading,
                filter: currentFilter,
                value,
                handleChange,
                optionValue,
                availableOptions,
            }
        }
    }
</script>
