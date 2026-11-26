import { setupWorker } from 'msw/browser'

// Handlers
import { handlerAppBarSearch } from '@db/app-bar-search/index'
import { handlerAppsAcademy } from '@db/apps/academy/index'
import { handlerAppsCalendar } from '@db/apps/calendar/index'
import { handlerAppsChat } from '@db/apps/chat/index'
import { handlerAppsEcommerce } from '@db/apps/ecommerce/index'
import { handlerAppsEmail } from '@db/apps/email/index'
import { handlerAppsInvoice } from '@db/apps/invoice/index'
import { handlerAppsKanban } from '@db/apps/kanban/index'
import { handlerAppLogistics } from '@db/apps/logistics/index'
import { handlerAppsPermission } from '@db/apps/permission/index'
import { handlerAppsUsers } from '@db/apps/users/index'
import { handlerDashboard } from '@db/dashboard/index'
import { handlerPagesDatatable } from '@db/pages/datatable/index'
import { handlerPagesFaq } from '@db/pages/faq/index'
import { handlerPagesHelpCenter } from '@db/pages/help-center/index'
import { handlerPagesProfile } from '@db/pages/profile/index'

const worker = setupWorker(
  ...handlerAppsEcommerce,
  ...handlerAppsAcademy,
  ...handlerAppsInvoice,
  ...handlerAppsUsers,
  ...handlerAppsEmail,
  ...handlerAppsCalendar,
  ...handlerAppsChat,
  ...handlerAppsPermission,
  ...handlerPagesHelpCenter,
  ...handlerPagesProfile,
  ...handlerPagesFaq,
  ...handlerPagesDatatable,
  ...handlerAppBarSearch,
  ...handlerAppLogistics,
  ...handlerAppsKanban,
  ...handlerDashboard,
)

export default function () {
  // 🛑 الـ Mock API (MSW) معطّل افتراضياً — المشروع يستخدم باك-إند Laravel حقيقي على /api.
  // كان يتسبب في تعطّل كل الطلبات (الخطوط، تسجيل الدخول...) بسبب تعارض msw مع مكتبة cookie.
  // لتشغيل البيانات الوهمية (صفحات القوالب التجريبية) فقط: اضبط VITE_ENABLE_MOCK_API=true في .env
  if (import.meta.env.VITE_ENABLE_MOCK_API !== 'true') {
    // إلغاء تسجيل أي Service Worker قديم للـ mock لا يزال نشطاً في المتصفح من جلسة سابقة
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(async registrations => {
        const mockReg = registrations.find(r => r.active?.scriptURL.includes('mockServiceWorker'))
        if (mockReg) {
          await mockReg.unregister()

          // الـ SW لا يزال يتحكم بالصفحة الحالية — إعادة تحميل واحدة لتحريره (مرة واحدة فقط)
          if (navigator.serviceWorker.controller && !sessionStorage.getItem('mock-sw-cleared')) {
            sessionStorage.setItem('mock-sw-cleared', '1')
            window.location.reload()
          }
        }
      })
    }

    return
  }

  const workerUrl = `${import.meta.env.BASE_URL.replace(/build\/$/g, '') ?? '/'}mockServiceWorker.js`

  worker.start({
    serviceWorker: {
      url: workerUrl,
    },
    onUnhandledRequest: 'bypass',
  })
}
