<script setup>
defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});
</script>

<template>
  <div class="base-table-wrap">
    <div v-if="loading" class="base-table-loading">Đang tải…</div>
    <div v-else class="base-table-scroll">
      <table class="base-table">
        <thead>
          <tr>
            <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, idx) in data" :key="item.id ?? idx">
            <td v-for="col in columns" :key="col.key">
              <slot :name="`cell(${col.key})`" :value="item[col.key]" :item="item">
                {{ item[col.key] }}
              </slot>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!data.length" class="base-table-empty">Không có dữ liệu.</p>
    </div>
  </div>
</template>

<style scoped>
.base-table-wrap {
  width: 100%;
  overflow: hidden;
}

.base-table-loading {
  padding: 2rem;
  text-align: center;
  color: #64748b;
  font-size: 14px;
}

.base-table-scroll {
  overflow-x: auto;
}

.base-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.base-table th,
.base-table td {
  border-bottom: 1px solid #e2e8f0;
  padding: 10px 12px;
  text-align: left;
  vertical-align: middle;
}

.base-table th {
  background: #f8fafc;
  font-weight: 600;
  color: #334155;
  white-space: nowrap;
}

.base-table tbody tr:hover {
  background: #f8fafc;
}

.base-table-empty {
  margin: 0;
  padding: 1.25rem;
  text-align: center;
  color: #94a3b8;
  font-size: 14px;
}
</style>
