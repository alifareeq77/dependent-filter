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
    import { ref, computed, watchEffect, onMounted } from 'vue'
    import { useStore } from 'vuex'
    import { useDebouncedRef } from 'vue-composables'
    import { filter, every, intersection, castArray } from 'lodash'

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

            const filter = computed(() => getFilter())
            const value = computed(() => filter.value.currentValue)

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
                let filteredOptions = filter(options.value, option => {
                    return !option.hasOwnProperty('depends') || every(option.depends, (values, filterName) => {
                        const filterObj = store.getters[`${props.resourceName}/getFilter`](filterName)
                        if (!filterObj) return true
                        return intersection(
                            castArray(filterObj.currentValue).map(String),
                            castArray(values).map(String)
                        ).length > 0;
                    })
                })

                if (!loading.value && value.value !== '' && filteredOptions.filter(option => option.value == value.value).length === 0) {
                    handleChange('')
                }

                return filteredOptions
            })

            const fetchOptions = async (filters) => {
                const lens = props.lens ? `/lens/${props.lens}` : ''
                try {
                    const { data } = await Nova.request().get(`/nova-api/${props.resourceName}${lens}/filters/options`, {
                        params: {
                            filters: btoa(JSON.stringify(filters)),
                            filter: props.filterKey,
                        },
                    })
                    options.value = data
                } catch (error) {
                    console.error('Error fetching filter options:', error)
                } finally {
                    loading.value = false
                }
            }

            // React to changes in dependent filters
            watchEffect(() => {
                const dependentFilters = filter.value.dependentOf.reduce((r, filterName) => {
                    const f = store.getters[`${props.resourceName}/getFilter`](filterName)
                    r[filterName] = f ? f.currentValue : ''
                    return r
                }, {})

                loading.value = true
                fetchOptions(dependentFilters)
            })

            onMounted(() => {
                options.value = filter.value.options
            })

            return {
                options,
                loading,
                filter,
                value,
                handleChange,
                optionValue,
                availableOptions,
            }
        }
    }
</script>
