import { useEffect } from 'react';
import { Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../stores/auth';
import AppLayout from '../layouts/AppLayout';
import Login from '../pages/Login';
import Dashboard from '../pages/Dashboard';
import CrudPage from '../features/crud/CrudPage';
import ItemPage from '../features/engineering/ItemPage';
import RouteTimePage from '../features/engineering/RouteTimePage';
import ProcessMainPage from '../features/engineering/ProcessMainPage';
import PrPage from '../features/procurement/PrPage';
import PoPage from '../features/procurement/PoPage';
import GrPage from '../features/procurement/GrPage';
import QuotaPage from '../features/procurement/QuotaPage';
import CostPage from '../features/procurement/CostPage';
import RejectPage from '../features/procurement/RejectPage';
import InvoicePage from '../features/procurement/InvoicePage';
import SubcontDnPage from '../features/procurement/SubcontDnPage';
import SubcontGrPage from '../features/procurement/SubcontGrPage';
import IncomingPage from '../features/wms/IncomingPage';
import OutgoingPage from '../features/wms/OutgoingPage';
import RemainingPage from '../features/wms/RemainingPage';
import StockRmPage from '../features/wms/StockRmPage';
import FgIncomingPage from '../features/wms/FgIncomingPage';
import FgOutgoingPage from '../features/wms/FgOutgoingPage';
import FgStockPage from '../features/wms/FgStockPage';
import WoPage from '../features/production/WoPage';
import MppPage from '../features/production/MppPage';
import MrpPage from '../features/production/MrpPage';
import CostRatePage from '../features/costing/CostRatePage';
import CogmPage from '../features/costing/CogmPage';
import AssetCategoryPage from '../features/costing/AssetCategoryPage';
import AssetPage from '../features/costing/AssetPage';
import CoaPage from '../features/accounting/CoaPage';
import PeriodPage from '../features/accounting/PeriodPage';
import JournalPage from '../features/accounting/JournalPage';
import ApPaymentPage from '../features/accounting/ApPaymentPage';
import ArReceiptPage from '../features/accounting/ArReceiptPage';
import MpsPage from '../features/production/MpsPage';
import MpsApprovalsPage from '../features/production/MpsApprovalsPage';
import CuttingPage from '../features/production/CuttingPage';
import ProcessingPage from '../features/production/ProcessingPage';
import AbnormalPage from '../features/production/AbnormalPage';
import MesReportPage from '../features/production/MesReportPage';
import ForecastPage from '../features/sales/ForecastPage';
import SoPage from '../features/sales/SoPage';
import PricelistPage from '../features/sales/PricelistPage';
import DeliveryOrderPage from '../features/sales/DeliveryOrderPage';
import SalesInvoicePage from '../features/sales/SalesInvoicePage';
import SalesReturnPage from '../features/sales/SalesReturnPage';
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
                <Route path="process-mains" element={<ProcessMainPage />} />
                <Route path="route-times" element={<RouteTimePage />} />
                <Route path="pr" element={<PrPage />} />
                <Route path="po" element={<PoPage />} />
                <Route path="grn" element={<GrPage />} />
                <Route path="quotas" element={<QuotaPage />} />
                <Route path="landed-costs" element={<CostPage />} />
                <Route path="gr-rejects" element={<RejectPage />} />
                <Route path="ap-invoices" element={<InvoicePage />} />
                <Route path="subcont-dn" element={<SubcontDnPage />} />
                <Route path="subcont-gr" element={<SubcontGrPage />} />
                <Route path="incoming-rm" element={<IncomingPage />} />
                <Route path="outgoing-rm" element={<OutgoingPage />} />
                <Route path="remaining-rm" element={<RemainingPage />} />
                <Route path="stock-rm" element={<StockRmPage />} />
                <Route path="incoming-fg" element={<FgIncomingPage />} />
                <Route path="outgoing-fg" element={<FgOutgoingPage />} />
                <Route path="stock-fg" element={<FgStockPage />} />
                <Route path="forecasts" element={<ForecastPage />} />
                <Route path="sales-orders" element={<SoPage />} />
                <Route path="pricelists" element={<PricelistPage />} />
                <Route path="delivery-orders" element={<DeliveryOrderPage />} />
                <Route path="sales-invoices" element={<SalesInvoicePage />} />
                <Route path="sales-returns" element={<SalesReturnPage />} />
                <Route path="mpp" element={<MppPage />} />
                <Route path="mrp" element={<MrpPage />} />
                <Route path="cost-rates" element={<CostRatePage />} />
                <Route path="cogm" element={<CogmPage />} />
                <Route path="asset-categs" element={<AssetCategoryPage />} />
                <Route path="assets" element={<AssetPage />} />
                <Route path="coa" element={<CoaPage />} />
                <Route path="acc-periods" element={<PeriodPage />} />
                <Route path="journals" element={<JournalPage />} />
                <Route path="ap-payments" element={<ApPaymentPage />} />
                <Route path="ar-receipts" element={<ArReceiptPage />} />
                <Route path="mps" element={<MpsPage />} />
                <Route path="mps-approvals" element={<MpsApprovalsPage />} />
                <Route path="mes-cutting" element={<CuttingPage />} />
                <Route path="mes-processing" element={<ProcessingPage />} />
                <Route path="mes-abnormal" element={<AbnormalPage />} />
                <Route path="mes-report" element={<MesReportPage />} />
                <Route path="work-orders" element={<WoPage />} />
                <Route path="*" element={<div className="p-6 text-slate-500">Halaman tidak ditemukan.</div>} />
            </Route>
        </Routes>
    );
}
