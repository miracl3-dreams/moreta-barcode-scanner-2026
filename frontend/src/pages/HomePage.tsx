import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '../auth'
import { AppShell } from '../components/AppShell'
import { PageLoader } from '../components/PageLoader'

export function HomePage() {
  const { user, loading } = useAuth()

  if (loading) {
    return <PageLoader label="Loading" />
  }

  if (!user) {
    return <Navigate to="/login" replace />
  }

  return (
    <AppShell>
      <Outlet />
    </AppShell>
  )
}
