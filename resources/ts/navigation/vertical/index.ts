import home from './home'
import locations from './locations'
import plants from './plants'
import users from './users'
import type { VerticalNavItems } from '@layouts/types'

export default [...home, ...plants, ...users, ...locations] as VerticalNavItems
