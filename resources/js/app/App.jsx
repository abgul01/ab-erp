import { useEffect } from 'react';
import { Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../stores/auth';
import AppLayout from '../layouts/AppLayout';
import Login from '../pages/Login';
import Dashboard from '../pages/Dashboard';
import CrudPage from '../features/crud/CrudPage';
import ItemPage from '../features/engineering/ItemPage';
import PrPage from '../features/procurement/PrPage';
import PoPage from '../features/procurement/PoPage';
import GrPage from '../features/procurement/GrPage';
import QuotaPage from '../features/procurement/QuotaPage';
import CostPage from '../features/procurement/CostPage';
import RejectPage from '../features/procurement/RejectPage';
import InvoicePage from '../features/procurement/InvoicePage';
import IncomingPage from '../features/wms/IncomingPage';
import OutgoingPage from '../features/wms/OutgoingPage';
import RemainingPage from '../features/wms/RemainingPage';
import StockRmPage from '../features/wms/StockRmPage';
import WoPage from '../features/production/WoPage';
import MppPage from '../features/production/MppPage';
import MpsPage from '../features/production/MpsPage';
import ForecastPage from '../features/sales/ForecastPage';
import SoPage from '../features/sales/SoPage';
import { RESOURCES } from '../features/crud/resources';
import Icon from '../components/Icon';

function ProtectedRoute({ children }) {
    const { token, ready } = useAuth();
    const location = useLocation();

    if (!ready) {
        return (
            <div className="flex h-screen items-center justify-center text-slate-400">
                <Icon name="spinner" className="h-6 w-6 animate-spin" />
            </div>
        );
    }
    if (!token) {
        return <Navigate to="/login" state={{ from: location }} replace />;
    }
    return children;
}

export default function App() {
    const { token, ready, loadMe } = useAuth();

    useEffect(() => {
        if (token) {
            loadMe();
        } else {
            useAuth.setState({ ready: true });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <Routes>
            <Route path="/login" element={<Login />} />
            <Route
                path="/"
                element={
                    <ProtectedRoute>
                        <AppLayout />
                    </ProtectedRoute>
                }
            >
                <Route index element={<Dashboard />} />
                {Object.values(RESOURCES)
                    .filter((r) => r.key !== 'items')
                    .map((r) => (
                        <Route key={r.key} path={r.key} element={<CrudPage resourceKey={r.key} />} />
                    ))}
                <Route path="items" element={<ItemPage />} />
                <Route path="pr" element={<PrPage />} />
                <Route path="po" element={<PoPage />} />
                <Route path="grn" element={<GrPage />} />
                <Route path="quotas" element={<QuotaPage />} />
                <Route path="landed-costs" element={<CostPage />} />
                <Route path="gr-rejects" element={<RejectPage />} />
                <Route path="ap-invoices" element={<InvoicePage />} />
                <Route path="incoming-rm" element={<IncomingPage />} />
                <Route path="outgoing-rm" element={<OutgoingPage />} />
                <Route path="remaining-rm" element={<RemainingPage />} />
                <Route path="stock-rm" element={<StockRmPage />} />
                <Route path="forecasts" element={<ForecastPage />} />
                <Route path="sales-orders" element={<SoPage />} />
                <Route path="mpp" element={<MppPage />} />
                <Route path="mps" element={<MpsPage />} />
                <Route path="work-orders" element={<WoPage />} />
                <Route path="*" element={<div className="p-6 text-slate-500">Halaman tidak ditemukan.</div>} />
            </Route>
        </Routes>
    );
}
