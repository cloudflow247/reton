import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './store/auth'
import { AppShell } from './components/AppShell'
import { Login } from './pages/Login'
import { Register } from './pages/Register'
import { Dashboard } from './pages/Dashboard'
import { Send } from './pages/Send'
import { AddMoney } from './pages/AddMoney'
import { SetPin } from './pages/SetPin'
import { Activity } from './pages/Activity'
import { Profile } from './pages/Profile'
import { Receive } from './pages/Receive'
import { PublicLayout } from './pages/public/PublicLayout'
import { Home } from './pages/public/Home'
import { Security } from './pages/public/Security'
import { HowItWorks } from './pages/public/HowItWorks'

export function App() {
  const token = useAuth((s) => s.token)

  if (!token) {
    return (
      <Routes>
        <Route path="/" element={<PublicLayout><Home /></PublicLayout>} />
        <Route path="/security" element={<PublicLayout><Security /></PublicLayout>} />
        <Route path="/how-it-works" element={<PublicLayout><HowItWorks /></PublicLayout>} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    )
  }

  return (
    <AppShell>
      <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route path="/send" element={<Send />} />
        <Route path="/add-money" element={<AddMoney />} />
        <Route path="/receive" element={<Receive />} />
        <Route path="/activity" element={<Activity />} />
        <Route path="/profile" element={<Profile />} />
        <Route path="/pin" element={<SetPin />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </AppShell>
  )
}
