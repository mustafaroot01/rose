// يجمع أيقونات Tabler المستخدمة فعلاً في المشروع + مجموعة منتقاة للتصنيفات،
// ويكتبها إلى tabler-icons.json — يقرأها build-icons.ts بدل تضمين كل الـ 6000 أيقونة
// (كانت icons.css وحدها ~2.8MB تُحمّل مع كل صفحة).
//
// التشغيل بعد إضافة أيقونات جديدة في الكود:
//   node resources/ts/plugins/iconify/scan-icons.mjs && pnpm build:icons
import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join, relative } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const root = join(here, '../../../..')
const require = createRequire(import.meta.url)

const tabler = JSON.parse(readFileSync(require.resolve('@iconify-json/tabler/icons.json'), 'utf8'))
const all = new Set([...Object.keys(tabler.icons), ...Object.keys(tabler.aliases ?? {})])

// ١) الأيقونات المذكورة في الكود (الواجهة + الخادم + البذور)
const scanDirs = ['resources/ts', 'app', 'database', 'config', 'routes']
const skip = new Set(['icons.css', 'tabler-icons.json'])
const used = new Set()

const walk = dir => {
  for (const name of readdirSync(dir)) {
    if (skip.has(name) || name === 'node_modules')
      continue
    const p = join(dir, name)
    if (statSync(p).isDirectory()) {
      walk(p)
      continue
    }
    if (!/\.(vue|ts|tsx|js|mjs|php|json)$/.test(name))
      continue
    for (const m of readFileSync(p, 'utf8').matchAll(/tabler-([a-z0-9]+(?:-[a-z0-9]+)*)/g))
      used.add(m[1])
  }
}
for (const d of scanDirs)
  walk(join(root, d))

// ٢) مجموعة منتقاة يختار منها المسؤول أيقونة التصنيف أو نوع الحساب
export const PICKER_KEYWORDS = [
  'tree', 'trees', 'plant', 'leaf', 'flower', 'seeding', 'sprout', 'apple', 'lemon', 'cherry',
  'carrot', 'cactus', 'mushroom', 'grain', 'wheat', 'clover', 'droplet', 'cloud-rain', 'sun',
  'shovel', 'bucket', 'recycle', 'world', 'mountain', 'butterfly', 'feather', 'bug', 'fish',
  'building', 'school', 'home', 'user', 'users', 'heart', 'star', 'map-pin', 'flag', 'award',
  'trophy', 'medal', 'briefcase', 'id-badge', 'shield',
]
const picker = [...all]
  .filter(n => !n.endsWith('-off') && PICKER_KEYWORDS.some(k => n === k || n.startsWith(`${k}-`)))
  .sort()

const missing = [...used].filter(n => !all.has(n))
const icons = [...new Set([...used, ...picker])].filter(n => all.has(n)).sort()

writeFileSync(join(here, 'tabler-icons.json'), `${JSON.stringify({ icons, picker }, null, 2)}\n`)
console.log(`used: ${used.size}, picker: ${picker.length}, bundled: ${icons.length}`)
if (missing.length)
  console.log(`not found in tabler (typos?): ${missing.join(', ')}`)
console.log(`written: ${relative(root, join(here, 'tabler-icons.json'))}`)
