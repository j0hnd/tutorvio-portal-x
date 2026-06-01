<template>
  <div class="radar-wrap">
    <canvas ref="canvasRef" :style="{ width: '100%', height: `${size}px` }" />
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
// Requires: npm install chart.js
import { Chart, RadarController, RadialLinearScale, PointElement, LineElement, Filler, Tooltip } from 'chart.js'

Chart.register(RadarController, RadialLinearScale, PointElement, LineElement, Filler, Tooltip)

const props = withDefaults(defineProps<{
  labels: string[]
  data: number[]
  max?: number
  size?: number
  color?: string
}>(), {
  max: 10,
  size: 280,
  color: '34, 139, 230',  // primary RGB fallback
})

const canvasRef = ref<HTMLCanvasElement | null>(null)
let chart: Chart | null = null

function getPrimaryRGB(): string {
  // Try to read CSS variable for primary color
  const style = getComputedStyle(document.documentElement)
  const h = style.getPropertyValue('--tv-primary-h').trim() || '210'
  const s = style.getPropertyValue('--tv-primary-s').trim() || '80%'
  const l = style.getPropertyValue('--tv-primary-l').trim() || '50%'
  // Convert HSL to approximate RGB for Chart.js
  return `hsl(${h}, ${s}, ${l})`
}

function buildChart() {
  const el = canvasRef.value
  if (!el) return
  const primary = getPrimaryRGB()

  chart = new Chart(el, {
    type: 'radar',
    data: {
      labels: props.labels,
      datasets: [{
        data: props.data,
        backgroundColor: `hsla(${getComputedStyle(document.documentElement).getPropertyValue('--tv-primary-h').trim() || '210'}, 70%, 55%, 0.15)`,
        borderColor: primary,
        borderWidth: 2,
        pointBackgroundColor: primary,
        pointBorderColor: 'white',
        pointBorderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 600, easing: 'easeInOutQuart' },
      scales: {
        r: {
          min: 0,
          max: props.max,
          ticks: {
            stepSize: 2,
            font: { size: 9 },
            color: '#999',
            backdropColor: 'transparent',
          },
          grid: { color: 'rgba(0,0,0,0.07)' },
          angleLines: { color: 'rgba(0,0,0,0.07)' },
          pointLabels: {
            font: { size: 11, weight: '600' },
            color: '#555',
          },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => ` ${ctx.parsed.r} / ${props.max}`,
          },
        },
      },
    },
  })
}

watch(() => props.data, (newData) => {
  if (!chart) return
  chart.data.datasets[0].data = newData
  chart.update()
}, { deep: true })

watch(() => props.labels, (newLabels) => {
  if (!chart) return
  chart.data.labels = newLabels
  chart.update()
})

onMounted(() => buildChart())
onUnmounted(() => { chart?.destroy(); chart = null })
</script>

<style scoped>
.radar-wrap {
  position: relative;
  width: 100%;
}
.radar-wrap canvas {
  display: block;
}
</style>
