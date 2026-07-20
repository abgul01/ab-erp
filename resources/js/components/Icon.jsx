import {
    Shield, Database, Tag, Tags, Ruler, Coins, Percent, Factory, Cog,
    Users, Workflow, Wrench, Box, Layers, LayoutDashboard, LogOut,
    Menu as MenuIcon, Search, Plus, Pencil, Trash2, X, ChevronDown, Loader2,
    ChevronLeft, ChevronRight, Save, ShoppingCart, ClipboardList, FileText,
    PackageCheck, Scale, Calculator, Check, Send, Ban, Lock,
    Undo2, Receipt, Warehouse, Rows3, Boxes, Calendar,
} from 'lucide-react';

const MAP = {
    shield: Shield, database: Database, tag: Tag, tags: Tags, ruler: Ruler,
    coins: Coins, percent: Percent, factory: Factory, cog: Cog, users: Users,
    workflow: Workflow, wrench: Wrench, box: Box, layers: Layers,
    dashboard: LayoutDashboard, logout: LogOut, menu: MenuIcon, search: Search,
    plus: Plus, pencil: Pencil, trash: Trash2, x: X, chevron: ChevronDown,
    left: ChevronLeft, right: ChevronRight, save: Save, spinner: Loader2,
    'shopping-cart': ShoppingCart, 'clipboard-list': ClipboardList,
    'file-text': FileText, 'package-check': PackageCheck, scale: Scale,
    calculator: Calculator, check: Check, send: Send, ban: Ban, lock: Lock,
    undo: Undo2, receipt: Receipt, warehouse: Warehouse, rows: Rows3, boxes: Boxes, calendar: Calendar,
};

export default function Icon({ name, className = 'h-4 w-4', ...rest }) {
    const Cmp = MAP[name] || Box;
    return <Cmp className={className} {...rest} />;
}
