import { useEffect } from 'react'

export type PageAction = 'List' | 'Add' | 'Edit' | 'View' | 'Return' | 'Access'

const APP_NAME = 'Moreta Barcode Scanner'

export function formatPageTitle(module: string, action?: PageAction | string): string {
  const moduleName = module.trim()
  const actionName = typeof action === 'string' ? action.trim() : ''

  if (!moduleName) {
    return actionName || APP_NAME
  }
  if (!actionName) {
    return moduleName
  }

  return `${moduleName} - ${actionName}`
}

export function pageActionFromMode(mode: string): PageAction | string {
  switch (mode) {
    case 'add':
      return 'Add'
    case 'edit':
      return 'Edit'
    case 'view':
      return 'View'
    case 'return':
      return 'Return'
    default:
      return mode
  }
}

export function usePageTitle(
  module: string,
  action?: PageAction | string,
  enabled = true,
): string {
  const pageTitle = formatPageTitle(module, action)

  useEffect(() => {
    if (!enabled) {
      return
    }
    document.title = pageTitle
    return () => {
      document.title = APP_NAME
    }
  }, [enabled, pageTitle])

  return pageTitle
}

export function crudPageAction(options: { formOpen: boolean; editing: boolean }): PageAction {
  return options.formOpen ? (options.editing ? 'Edit' : 'Add') : 'List'
}

export function formModePageAction(formOpen: boolean, mode: string): PageAction | string {
  return formOpen ? pageActionFromMode(mode) : 'List'
}
