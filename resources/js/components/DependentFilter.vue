<template>
    <div v-show="loading || !filter.hideWhenEmpty || availableOptions.length > 0">
        <h3 class="text-sm uppercase tracking-wide text-80 bg-30 p-3">{{ filter.name }}</h3>

        <div class="p-2">
            <div style="margin-bottom: 10px; font-size: 12px; color: #666;">
                Debug: {{ availableOptions.length }} options available
            </div>
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
            console.log('[DependentFilter] Setup called with props:', props)
            
            const store = useStore()
            const options = ref([])
            const loading = ref(false)

            const getFilter = () => {
                const filter = store.getters[`${props.resourceName}/getFilter`](props.filterKey)
                console.log('[DependentFilter] getFilter called:', filter)
                return filter
            }

            const currentFilter = computed(() => {
                const filter = getFilter()
                console.log('[DependentFilter] currentFilter computed:', filter)
                return filter
            })
            
            const value = computed(() => {
                const val = currentFilter.value.currentValue
                console.log('[DependentFilter] value computed:', val)
                return val
            })

            const handleChange = (value) => {
                console.log('[DependentFilter] handleChange called with value:', value)
                
                store.commit(`${props.resourceName}/updateFilterState`, {
                    filterClass: props.filterKey,
                    value: value,
                })

                emit('change')
                console.log('[DependentFilter] Change emitted')
            }

            const availableOptions = computed(() => {
                console.log('[DependentFilter] availableOptions computing from options.value:', options.value)
                
                const filtered = lodashFilter(options.value, option => {
                    if (!option.hasOwnProperty('depends')) {
                        console.log('[DependentFilter] Option has no depends:', option)
                        return true
                    }
                    
                    const result = every(option.depends, (values, filterName) => {
                        const filterObj = store.getters[`${props.resourceName}/getFilter`](filterName)
                        if (!filterObj) {
                            console.log('[DependentFilter] Filter not found:', filterName)
                            return true
                        }
                        
                        const currentValue = filterObj.currentValue
                        if (currentValue === '' || currentValue === null) {
                            console.log('[DependentFilter] Filter has empty value:', filterName, currentValue)
                            return false
                        }
                        
                        const intersectionResult = intersection(
                            castArray(currentValue).map(String),
                            castArray(values).map(String)
                        ).length > 0
                        
                        console.log('[DependentFilter] Dependency check:', filterName, currentValue, values, intersectionResult)
                        return intersectionResult
                    })
                    
                    console.log('[DependentFilter] Option filter result:', option, result)
                    return result
                })
                
                console.log('[DependentFilter] availableOptions result:', filtered)
                return filtered
            })

            const fetchOptions = async (filters) => {
                console.log('[DependentFilter] fetchOptions called with filters:', filters)
                
                const lens = props.lens ? `/lens/${props.lens}` : ''
                const url = `/nova-api/${props.resourceName}${lens}/filters/options`
                
                console.log('[DependentFilter] Fetching from URL:', url)
                loading.value = true
                
                try {
                    const { data } = await Nova.request().get(url, {
                        params: {
                            filters: btoa(JSON.stringify(filters)),
                            filter: props.filterKey,
                        },
                    })
                    
                    console.log('[DependentFilter] Fetch successful, data:', data)
                    options.value = data
                    
                    // Check if current value is still valid after fetching new options
                    const current = value.value
                    console.log('[DependentFilter] Checking current value validity:', current)
                    
                    if (current !== '' && !data.some(o => String(o.value) === String(current))) {
                        console.log('[DependentFilter] Current value is invalid, resetting')
                        handleChange('')
                    }
                } catch (error) {
                    console.error('[DependentFilter] Error fetching filter options:', error)
                    options.value = []
                } finally {
                    loading.value = false
                    console.log('[DependentFilter] Loading complete')
                }
            }

            // React to changes in dependent filters' values
            watch(
                () => {
                    const dependentFilters = currentFilter.value.dependentOf
                    console.log('[DependentFilter] Watch evaluating dependentOf:', dependentFilters)
                    
                    if (dependentFilters.length === 0) return []
                    
                    const values = dependentFilters.map((name) => {
                        const f = store.getters[`${props.resourceName}/getFilter`](name)
                        const val = f ? f.currentValue : ''
                        console.log('[DependentFilter] Watch - dependent filter value:', name, val)
                        return val
                    })
                    
                    console.log('[DependentFilter] Watch returning values:', values)
                    return values
                },
                (values) => {
                    console.log('[DependentFilter] Watch triggered with values:', values)
                    
                    const dependentFilters = currentFilter.value.dependentOf
                    if (dependentFilters.length === 0) {
                        console.log('[DependentFilter] No dependent filters, skipping')
                        return
                    }
                    
                    const filters = dependentFilters.reduce((r, name, idx) => {
                        r[name] = values[idx]
                        return r
                    }, {})
                    
                    console.log('[DependentFilter] Watch calling fetchOptions with:', filters)
                    fetchOptions(filters)
                }
            )

            onMounted(() => {
                console.log('[DependentFilter] onMounted called')
                
                const current = currentFilter.value
                console.log('[DependentFilter] Current filter on mount:', current)
                
                // For filters with dependencies, fetch options
                if (current.dependentOf && current.dependentOf.length > 0) {
                    console.log('[DependentFilter] Has dependencies, fetching options')
                    
                    const filters = current.dependentOf.reduce((r, name) => {
                        const f = store.getters[`${props.resourceName}/getFilter`](name)
                        r[name] = f ? f.currentValue : ''
                        console.log('[DependentFilter] Dependent filter on mount:', name, r[name])
                        return r
                    }, {})
                    
                    console.log('[DependentFilter] Calling fetchOptions on mount with:', filters)
                    fetchOptions(filters)
                } else {
                    // For filters without dependencies, use the options from the filter object
                    console.log('[DependentFilter] No dependencies, using filter options:', current.options)
                    options.value = current.options || []
                    console.log('[DependentFilter] Options set to:', options.value)
                }
            })

            return {
                options,
                loading,
                filter: currentFilter,
                value,
                handleChange,
                availableOptions,
            }
        }
    }
</script>