import { useEffect } from 'react';
import { Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../stores/auth';
import AppLayout from '../layouts/AppLayout';
import Login from '../pages/Login';
import Dashboard from '../pages/Dashboard';
import CrudPage from '../features/crud/CrudPage';
import ApprovalPage from '../features/approval/ApprovalPage';
import ItemPage from '../features/engineering/ItemPage';
import RouteTimePage from '../features/engineering/RouteTimePage';
import ProcessMainPage from '../features/engineering/ProcessMainPage';
import PrPage from '../features/procurement/PrPage';
import PoPage from '../features/procurement/PoPage';
import GrPage from '../features/procurement/GrPage';
import QuotaPage from '../features/procurement/QuotaPage';
import QuotationPage from '../features/procurement/QuotationPage';
import ContractPage from '../features/procurement/ContractPage';
import CostPage from '../features/procurement/CostPage';
import RejectPage from '../features/procurement/RejectPage';
import InvoicePage from '../features/procurement/InvoicePage';
import SubcontDnPage from '../features/procurement/SubcontDnPage';
import SubcontGrPage from '../features/procurement/SubcontGrPage';
import SubcontItemPage from '../features/procurement/SubcontItemPage';
import SubcontPoPage from '../features/procurement/SubcontPoPage';
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
import ValuationPage from '../features/costing/ValuationPage';
import AssetCategoryPage from '../features/costing/AssetCategoryPage';
import AssetPage from '../features/costing/AssetPage';
import CoaPage from '../features/accounting/CoaPage';
import PeriodPage from '../features/accounting/PeriodPage';
import JournalPage from '../features/accounting/JournalPage';
import ApPaymentPage from '../features/accounting/ApPaymentPage';
import ArReceiptPage from '../features/accounting/ArReceiptPage';
import TaxExportPage from '../features/accounting/TaxExportPage';
import WorkCalendarPage from '../features/engineering/WorkCalendarPage';
import EcnPage from '../features/engineering/EcnPage';
import NpdRfqPage from '../features/npd/NpdRfqPage';
import NpdProjectPage from '../features/npd/NpdProjectPage';
import NpdTaskPage from '../features/npd/NpdTaskPage';
import NpdCostingPage from '../features/npd/NpdCostingPage';
import NpdTrialPage from '../features/npd/NpdTrialPage';
import NpdQualityPage from '../features/npd/NpdQualityPage';
import NpdPpapPage from '../features/npd/NpdPpapPage';
import NpdReportPage from '../features/npd/NpdReportPage';
import NpdHandoverPage from '../features/npd/NpdHandoverPage';
import QasPage from '../features/procurement/QasPage';
import AlertPage from '../features/procurement/AlertPage';
import CrpPage from '../features/production/CrpPage';
import KanbanPage from '../features/production/KanbanPage';
import FcsPage from '../features/production/FcsPage';
import FgTransferPage from '../features/wms/FgTransferPage';
import FgDowngradePage from '../features/wms/FgDowngradePage';
import WhsPoPage from '../features/whs/WhsPoPage';
import WhsIncomingPage from '../features/whs/WhsIncomingPage';
import WhsOutgoingPage from '../features/whs/WhsOutgoingPage';
import WhsReturnPage from '../features/whs/WhsReturnPage';
import WhsStockPage from '../features/whs/WhsStockPage';
import PutawayPage from '../features/wms/PutawayPage';
import ScrapPage from '../features/wms/ScrapPage';
import StockAdjustmentPage from '../features/wms/StockAdjustmentPage';
import FinReportPage from '../features/accounting/FinReportPage';
import ForecastAnalysisPage from '../features/sales/ForecastAnalysisPage';
import BomToolsPage from '../features/engineering/BomToolsPage';
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
import PackingListPage from '../features/sales/PackingListPage';
import ShippingOrderPage from '../features/sales/ShippingOrderPage';
import SalesInvoicePage from '../features/sales/SalesInvoicePage';
import SalesReturnPage from '../features/sales/SalesReturnPage';
import { RESOURCES } from '../features/crud/resources';
import Icon from '../components/Icon';
import UsersPage from '../features/admin/UsersPage';
import MenusPage from '../features/admin/MenusPage';

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
                <Route path="quotations" element={<QuotationPage />} />
                <Route path="contracts" element={<ContractPage />} />
                <Route path="landed-costs" element={<CostPage />} />
                <Route path="gr-rejects" element={<RejectPage />} />
                <Route path="ap-invoices" element={<InvoicePage />} />
                <Route path="subcont-items" element={<SubcontItemPage />} />
                <Route path="subcont-po" element={<SubcontPoPage />} />
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
                <Route path="packing-lists" element={<PackingListPage />} />
                <Route path="shipping-orders" element={<ShippingOrderPage />} />
                <Route path="sales-invoices" element={<SalesInvoicePage />} />
                <Route path="sales-returns" element={<SalesReturnPage />} />
                <Route path="mpp" element={<MppPage />} />
                <Route path="mrp" element={<MrpPage />} />
                <Route path="cost-rates" element={<CostRatePage />} />
                <Route path="cogm" element={<CogmPage />} />
                <Route path="inventory-valuation" element={<ValuationPage />} />
                <Route path="asset-categs" element={<AssetCategoryPage />} />
                <Route path="assets" element={<AssetPage />} />
                <Route path="coa" element={<CoaPage />} />
                <Route path="acc-periods" element={<PeriodPage />} />
                <Route path="journals" element={<JournalPage />} />
                <Route path="ap-payments" element={<ApPaymentPage />} />
                <Route path="ar-receipts" element={<ArReceiptPage />} />
                <Route path="tax-export" element={<TaxExportPage />} />
                <Route path="work-calendar" element={<WorkCalendarPage />} />
                <Route path="ecn" element={<EcnPage />} />
                <Route path="npd-rfq" element={<NpdRfqPage />} />
                <Route path="npd-projects" element={<NpdProjectPage />} />
                <Route path="npd-tasks" element={<NpdTaskPage />} />
                <Route path="npd-costing" element={<NpdCostingPage />} />
                <Route path="npd-trials" element={<NpdTrialPage />} />
                {/* Satu komponen, dua menu: control plan disusun dari FMEA. */}
                <Route path="npd-fmea" element={<NpdQualityPage tab="fmea" />} />
                <Route path="npd-control-plan" element={<NpdQualityPage tab="cp" />} />
                <Route path="npd-ppap" element={<NpdPpapPage />} />
                <Route path="npd-reports" element={<NpdReportPage />} />
                <Route path="npd-handover" element={<NpdHandoverPage />} />
                <Route path="qas" element={<QasPage />} />
                <Route path="alerts" element={<AlertPage />} />
                <Route path="crp" element={<CrpPage />} />
                <Route path="kanbans" element={<KanbanPage />} />
                <Route path="fcs" element={<FcsPage />} />
                <Route path="fg-transfer" element={<FgTransferPage />} />
                <Route path="fg-downgrade" element={<FgDowngradePage />} />
                <Route path="whs-po" element={<WhsPoPage />} />
                <Route path="whs-incoming" element={<WhsIncomingPage />} />
                <Route path="whs-outgoing" element={<WhsOutgoingPage />} />
                <Route path="whs-returns" element={<WhsReturnPage />} />
                <Route path="whs-stock" element={<WhsStockPage />} />
                <Route path="putaway" element={<PutawayPage />} />
                <Route path="scrap-rm" element={<ScrapPage />} />
                <Route path="stock-adjustments" element={<StockAdjustmentPage />} />
                <Route path="fin-reports" element={<FinReportPage />} />
                <Route path="forecast-analysis" element={<ForecastAnalysisPage />} />
                <Route path="bom-tools" element={<BomToolsPage />} />
                <Route path="mps" element={<MpsPage />} />
                <Route path="mps-approvals" element={<MpsApprovalsPage />} />
                <Route path="mes-cutting" element={<CuttingPage />} />
                <Route path="mes-processing" element={<ProcessingPage />} />
                <Route path="mes-abnormal" element={<AbnormalPage />} />
                <Route path="mes-report" element={<MesReportPage />} />
                <Route path="approvals" element={<ApprovalPage />} />
                <Route path="users" element={<UsersPage />} />
                <Route path="menus" element={<MenusPage />} />
                <Route path="work-orders" element={<WoPage />} />
                <Route path="*" element={<div className="p-6 text-slate-500">Halaman tidak ditemukan.</div>} />
            </Route>
        </Routes>
    );
}
