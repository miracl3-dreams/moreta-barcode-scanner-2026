import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router-dom'
import { APP_BASE } from './lib/routes'
import { AuthProvider } from './auth'
import { QueryProvider } from './components/QueryProvider'
import { ToastProvider } from './components/ui/ToastProvider'
import { ThemeProvider } from './theme-context'
import { HomePage } from './pages/HomePage'
import { LoginPage } from './pages/LoginPage'
import { DashboardHome } from './pages/DashboardHome'
import { BarcodeScannerPage } from './pages/BarcodeScannerPage'
import { EirSigningListPage } from './pages/EirSigningListPage'
import { EirSigningPadPage } from './pages/EirSigningPadPage'

function RedirectLegacyEirSigning() {
  const { recid } = useParams()
  return <Navigate to={`/data-entry/eir-signing/${recid ?? ''}/edit`} replace />
}

export default function App() {
  return (
    <ThemeProvider>
    <QueryProvider>
      <ToastProvider>
        <AuthProvider>
          <BrowserRouter basename={APP_BASE}>
            <Routes>
              <Route path="/login" element={<LoginPage />} />
              <Route path="/login.php" element={<Navigate to="/login" replace />} />
              <Route path="/" element={<Navigate to="/dashboard" replace />} />
              <Route element={<HomePage />}>
                <Route path="/dashboard" element={<DashboardHome />} />
                <Route path="/data-entry/scanner" element={<BarcodeScannerPage title="Barcode Scanner" />} />
                <Route path="/data-entry/eir-signing" element={<EirSigningListPage />} />
                <Route path="/data-entry/eir-signing/:recid/edit" element={<EirSigningPadPage />} />
                <Route path="/data-entry/eir-signing/:recid" element={<RedirectLegacyEirSigning />} />
                {/* Legacy short paths */}
                <Route path="/scanner" element={<Navigate to="/data-entry/scanner" replace />} />
                <Route path="/eir-signing" element={<Navigate to="/data-entry/eir-signing" replace />} />
                <Route path="/eir-signing/:recid" element={<RedirectLegacyEirSigning />} />
              </Route>
              <Route path="*" element={<Navigate to="/dashboard" replace />} />
            </Routes>
          </BrowserRouter>
        </AuthProvider>
      </ToastProvider>
    </QueryProvider>
    </ThemeProvider>
  )
}
