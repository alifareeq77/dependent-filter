<template>
    <div v-show="loading || !filter.hideWhenEmpty || availableOptions.length > 0">
        <h3 class="text-sm uppercase tracking-wide text-80 bg-30 p-3">{{ filter.name }}</h3>

        <div class="p-2">
            <select-control
                    :dusk="`${filter.name}-filter-select`"
                    class="block w-full form-control-sm form-select"
                    :value="value"
                    @change="handleChange($event.target.value)"
                    :options="availableOptions"
                    :label="optionValue"
                    :selected="value"
            >
                <option value="" selected>&mdash;</option>
            </select-control>
        </div>
    </div>
</template>

<script>
    import { ref, computed, watch, onMounted } from 'vue'
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
            const options = ref([])
            const loading = ref(false)

            const getFilter = () => {
                return store.getters[`${props.resourceName}/getFilter`](props.filterKey)
            }

            const currentFilter = computed(() => getFilter())
            const value = computed(() => currentFilter.value.currentValue)

            const optionValue = (option) => {
                return option.label || option.name || option.value
            }

            const handleChange = (value) => {
                store.commit(`${props.resourceName}/updateFilterState`, {
                    filterClass: props.filterKey,
                    value: value,
                })

                emit('change')
            }

            const availableOptions = computed(() => {
                return lodashFilter(options.value, option => {
                    if (!option.hasOwnProperty('depends')) return true
                    
                    return every(option.depends, (values, filterName) => {
                        const filterObj = store.getters[`${props.resourceName}/getFilter`](filterName)
                        if (!filterObj) return true
                        
                        const currentValue = filterObj.currentValue
                        if (currentValue === '' || currentValue === null) return false
                        
                        return intersection(
                            castArray(currentValue).map(String),
                            castArray(values).map(String)
                        ).length > 0
                    })
                })
            })

            const fetchOptions = async (filters) => {
                const lens = props.lens ? `/lens/${props.lens}` : ''
                loading.value = true
                
                try {
                    const { data } = await Nova.request().get(`/nova-api/${props.resourceName}${lens}/filters/options`, {
                        params: {
                            filters: btoa(JSON.stringify(filters)),
                            filter: props.filterKey,
                        },
                    })
                    options.value = data
                    
                    // Check if current value is still valid after fetching new options
                    const current = value.value
                    if (current !== '' && !data.some(o => String(o.value) === String(current))) {
                        handleChange('')
                    }
                } catch (error) {
                    console.error('Error fetching filter options:', error)
                    options.value = []
                } finally {
                    loading.value = false
                }
            }

            // React to changes in dependent filters' values
            watch(
                () => {
                    const dependentFilters = currentFilter.value.dependentOf
                    if (dependentFilters.length === 0) return []
                    
                    return dependentFilters.map((name) => {
                        const f = store.getters[`${props.resourceName}/getFilter`](name)
                        return f ? f.currentValue : ''
                    })
                },
                (values) => {
                    const dependentFilters = currentFilter.value.dependentOf
                    if (dependentFilters.length === 0) return
                    
                    const filters = dependentFilters.reduce((r, name, idx) => {
                        r[name] = values[idx]
                        return r
                    }, {})

                    fetchOptions(filters)
                }
            )

            onMounted(() => {
                const current = currentFilter.value
                
                // For filters with dependencies, fetch options
                if (current.dependentOf && current.dependentOf.length > 0) {
                    const filters = current.dependentOf.reduce((r, name) => {
                        const f = store.getters[`${props.resourceName}/getFilter`](name)
                        r[name] = f ? f.currentValue : ''
                        return r
                    }, {})
                    
                    fetchOptions(filters)
                } else {
                    // For filters without dependencies, use the options from the filter object
                    options.value = current.options || []
                }
            })

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