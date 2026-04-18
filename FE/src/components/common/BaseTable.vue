<script setup>
defineProps({
  columns: {
    type: Array,
    required: true,
    // Format: [{ key: 'id', label: 'ID', sortable: false }]
  },
  data: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false
  }
});
defineEmits(["row-click"]);
</script>

<template>
  <div class="base-table-container">
    <table class="base-table">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.key" class="base-table-th">
            {{ col.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td :colspan="columns.length" class="text-center py-8">
            <div class="spinner-wrapper"><div class="spinner"></div></div>
          </td>
        </tr>
        <tr v-else-if="data.length === 0">
          <td :colspan="columns.length" class="text-center py-8 empty-text">
            Không có dữ liệu
          </td>
        </tr>
        <tr v-else v-for="(row, index) in data" :key="index" class="base-table-tr" @click="$emit('row-click', row)" style="cursor: pointer">
          <td v-for="col in columns" :key="col.key" class="base-table-td">
            <slot :name="`cell(${col.key})`" :value="row[col.key]" :item="row">
              {{ row[col.key] }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.base-table-container {
  width: 100%;
  overflow: visible;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
  font-family: 'Inter', system-ui, sans-serif;
  background: white;
}

.base-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.base-table-th {
  background-color: #f8fafc;
  padding: 1rem 1.25rem;
  font-weight: 600;
  font-size: 0.875rem;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 2px solid #e2e8f0;
}

.base-table-td {
  padding: 1rem 1.25rem;
  font-size: 0.95rem;
  color: #334155;
  border-bottom: 1px solid #f1f5f9;
}

.base-table-tr {
  transition: background-color 0.15s ease;
}

.base-table-tr:hover {
  background-color: #f8fafc;
}

.base-table-tr:last-child .base-table-td {
  border-bottom: none;
}

.text-center {
  text-align: center;
}

.py-8 {
  padding-top: 2rem;
  padding-bottom: 2rem;
}

.empty-text {
  color: #94a3b8;
  font-style: italic;
}

.spinner-wrapper {
  display: flex;
  justify-content: center;
}

.spinner {
  border: 3px solid #f3f3f3;
  border-top: 3px solid #4f46e5;
  border-radius: 50%;
  width: 24px;
  height: 24px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
