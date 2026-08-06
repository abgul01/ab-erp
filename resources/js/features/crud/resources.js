/**
 * Declarative config for config-driven CRUD screens.
 * key = route path = API endpoint segment = menu link (must match backend perm middleware).
 *
 * columns: [{ key, label, render? }]
 * fields:  [{ name, label, type, required?, options?, optionsFrom?, min?, step?, colSpan? }]
 *   type ∈ text | number | textarea | checkbox | select
 *   optionsFrom: { resource, valueKey, labelKey } → async select populated from another endpoint
 */
export const RESOURCES = {
    /**
     * Keluarga produk: pengelompokan komersial beberapa part sejenis.
     * Dipakai laporan margin untuk menjawab "keluarga ini untung atau tidak".
     */
    'product-families': {
        key: 'product-families',
        title: 'Product Family',
        singular: 'Keluarga Produk',
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Nama' },
            { key: 'descrip', label: 'Keterangan' },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'code', label: 'Kode', type: 'text', required: true },
            { name: 'name', label: 'Nama', type: 'text', required: true },
            { name: 'descrip', label: 'Keterangan', type: 'text' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },

    /**
     * Lintasan produksi: sekumpulan mesin yang kapasitasnya dinilai bersama.
     * CRP memakainya untuk beban per lintasan, bukan hanya per mesin.
     */
    'production-lines': {
        key: 'production-lines',
        title: 'Production Line',
        singular: 'Lintasan Produksi',
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Nama' },
            { key: 'daily_hours', label: 'Jam/hari' },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'code', label: 'Kode', type: 'text', required: true },
            { name: 'name', label: 'Nama', type: 'text', required: true },
            { name: 'descrip', label: 'Keterangan', type: 'text' },
            { name: 'daily_hours', label: 'Jam kerja per hari', type: 'number', step: '0.5', default: 16 },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },

    categories: {
        key: 'categories',
        title: 'Item Category',
        singular: 'Kategori',
        columns: [
            { key: 'id', label: 'ID' },
            { key: 'name_c', label: 'Nama Kategori' },
        ],
        fields: [{ name: 'name_c', label: 'Nama Kategori', type: 'text', required: true }],
    },
    uoms: {
        key: 'uoms',
        title: 'Unit of Measure',
        singular: 'UoM',
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Nama' },
            { key: 'uom_type', label: 'Tipe' },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'code', label: 'Kode', type: 'text', required: true },
            { name: 'name', label: 'Nama', type: 'text', required: true },
            { name: 'uom_type', label: 'Tipe (COUNT/WEIGHT/LENGTH)', type: 'text' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },
    currencies: {
        key: 'currencies',
        title: 'Currency',
        singular: 'Currency',
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Nama' },
            { key: 'is_base', label: 'Base', render: (v) => (v ? 'Ya' : '') },
        ],
        fields: [
            { name: 'code', label: 'Kode (3 huruf)', type: 'text', required: true },
            { name: 'name', label: 'Nama', type: 'text', required: true },
            { name: 'is_base', label: 'Mata Uang Dasar', type: 'checkbox' },
        ],
    },
    'supplier-items': {
        key: 'supplier-items',
        title: 'Supplier Item & Price',
        singular: 'Harga Supplier',
        // MRP reads the lowest-priority row to decide the order quantity and
        // when the buyer has to act, so these numbers drive purchasing.
        columns: [
            { key: 'vendor', label: 'Supplier', render: (v) => v?.company_n || '—' },
            { key: 'item', label: 'Material', render: (v) => (v ? `${v.code} — ${v.part_name}` : '—') },
            { key: 'priority', label: 'Prioritas' },
            { key: 'price', label: 'Harga', render: (v) => Number(v || 0).toLocaleString('id-ID') },
            { key: 'moq', label: 'MOQ' },
            { key: 'order_lot', label: 'Kelipatan' },
            { key: 'lead_time_days', label: 'Lead (hari)' },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'ven_id', label: 'Supplier', type: 'select', required: true,
              optionsFrom: { resource: 'contacts', valueKey: 'id', labelKey: 'company_n' } },
            { name: 'item_id', label: 'Material', type: 'select', required: true,
              optionsFrom: { resource: 'items', valueKey: 'id', labelKey: 'code' } },
            { name: 'priority', label: 'Prioritas (1 = utama)', type: 'number', default: 1 },
            { name: 'price', label: 'Harga per pcs', type: 'number', step: '0.01' },
            { name: 'currency_id', label: 'Mata Uang', type: 'select',
              optionsFrom: { resource: 'currencies', valueKey: 'id', labelKey: 'code' } },
            { name: 'moq', label: 'MOQ (0 = bebas)', type: 'number' },
            { name: 'order_lot', label: 'Kelipatan pemesanan (0 = bebas)', type: 'number' },
            { name: 'lead_time_days', label: 'Lead time (hari)', type: 'number' },
            { name: 'supplier_part_no', label: 'Part No Supplier', type: 'text' },
            { name: 'valid_from', label: 'Berlaku Dari', type: 'text' },
            { name: 'valid_to', label: 'Berlaku Sampai', type: 'text' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },
    'whs-items': {
        key: 'whs-items',
        title: 'Master Barang WHS',
        singular: 'Barang WHS',
        // Master gudang non-material: sparepart, barang habis pakai, dan alat.
        // Terpisah dari item produksi karena tidak punya BOM, routing, atau MRP.
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Nama' },
            { key: 'whs_type', label: 'Jenis' },
            { key: 'categ', label: 'Kategori' },
            { key: 'brand', label: 'Merek' },
            { key: 'rack_loc', label: 'Lokasi' },
            { key: 'min_stock', label: 'Min. Stok' },
            { key: 'standard_cost', label: 'Harga Acuan', render: (v) => Number(v || 0).toLocaleString('id-ID') },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'code', label: 'Kode', type: 'text', required: true },
            { name: 'name', label: 'Nama Barang', type: 'text', required: true },
            { name: 'whs_type', label: 'Jenis', type: 'select', required: true, default: 'CONSUMABLE',
              options: [
                  { value: 'PART', label: 'PART — sparepart mesin' },
                  { value: 'CONSUMABLE', label: 'CONSUMABLE — habis pakai' },
                  { value: 'TOOL', label: 'TOOL — alat kerja (dipinjam & dikembalikan)' },
              ] },
            { name: 'categ', label: 'Kategori', type: 'text' },
            { name: 'uom_id', label: 'Satuan', type: 'select',
              optionsFrom: { resource: 'uoms', valueKey: 'id', labelKey: 'code' } },
            { name: 'brand', label: 'Merek', type: 'text' },
            { name: 'spec', label: 'Spesifikasi', type: 'text' },
            { name: 'rack_loc', label: 'Lokasi Rak', type: 'text' },
            { name: 'min_stock', label: 'Stok Minimum', type: 'number' },
            { name: 'max_stock', label: 'Stok Maksimum', type: 'number' },
            { name: 'standard_cost', label: 'Harga Acuan (dipakai sebelum ada penerimaan)', type: 'number', step: '0.01' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },
    'inspection-params': {
        key: 'inspection-params',
        title: 'Parameter Inspeksi',
        singular: 'Parameter',
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Nama' },
            { key: 'uom', label: 'Satuan' },
            { key: 'method', label: 'Metode Ukur' },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'code', label: 'Kode', type: 'text', required: true },
            { name: 'name', label: 'Nama Parameter', type: 'text', required: true },
            { name: 'uom', label: 'Satuan', type: 'text' },
            { name: 'method', label: 'Metode Pengukuran', type: 'text' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },
    defectives: {
        key: 'defectives',
        title: 'Kode Defect',
        singular: 'Defect',
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Nama' },
            { key: 'type', label: 'Tipe' },
        ],
        fields: [
            { name: 'code', label: 'Kode', type: 'text', required: true },
            { name: 'name', label: 'Nama Defect', type: 'text', required: true },
            { name: 'type', label: 'Tipe (INCOMING/PROCESS/FINAL)', type: 'text' },
        ],
    },
    'exchange-rates': {
        key: 'exchange-rates',
        title: 'Kurs Pajak & Bank',
        singular: 'Kurs',
        // KMK is the weekly rate the tax office publishes; it governs customs
        // and import VAT, and a document uses the last one published on or
        // before its own date.
        columns: [
            { key: 'valid_date', label: 'Berlaku Sejak', render: (v) => (v || '').slice(0, 10) },
            { key: 'rate_type', label: 'Jenis' },
            { key: 'currency', label: 'Mata Uang', render: (v) => v?.code || '—' },
            { key: 'rate', label: 'Kurs (Rp)', render: (v) => Number(v || 0).toLocaleString('id-ID') },
        ],
        fields: [
            { name: 'valid_date', label: 'Berlaku Sejak', type: 'text', required: true },
            {
                name: 'rate_type', label: 'Jenis Kurs', type: 'select', required: true,
                options: [{ value: 'KMK', label: 'KMK (pajak)' }, { value: 'BANK', label: 'Bank' }],
            },
            {
                name: 'currency_id', label: 'Mata Uang', type: 'select', required: true,
                optionsFrom: { resource: 'currencies', valueKey: 'id', labelKey: 'code' },
            },
            { name: 'rate', label: 'Kurs terhadap Rupiah', type: 'number', step: '0.0001', required: true },
        ],
    },
    taxes: {
        key: 'taxes',
        title: 'Tax Code',
        singular: 'Tax Code',
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Nama' },
            { key: 'rate_pct', label: 'Rate %' },
            { key: 'dpp_factor', label: 'DPP Factor' },
            { key: 'is_luxury', label: 'Mewah', render: (v) => (v ? 'Ya' : '') },
        ],
        fields: [
            { name: 'code', label: 'Kode', type: 'text', required: true },
            { name: 'name', label: 'Nama', type: 'text', required: true },
            { name: 'rate_pct', label: 'Rate (%)', type: 'number', step: '0.0001', required: true },
            { name: 'dpp_factor', label: 'DPP Factor (11/12 = 0.916667)', type: 'number', step: '0.000001', required: true },
            { name: 'is_luxury', label: 'Barang Mewah', type: 'checkbox' },
            { name: 'effective_from', label: 'Berlaku Dari', type: 'date' },
        ],
    },
    makers: {
        key: 'makers',
        title: 'Maker',
        singular: 'Maker',
        columns: [
            { key: 'id', label: 'ID' },
            { key: 'name', label: 'Nama' },
            { key: 'address', label: 'Alamat' },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'name', label: 'Nama', type: 'text', required: true },
            { name: 'address', label: 'Alamat', type: 'textarea' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },
    machines: {
        key: 'machines',
        title: 'Machine',
        singular: 'Mesin',
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name', label: 'Nama' },
            { key: 'model', label: 'Model' },
            { key: 'categ', label: 'Kategori' },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'code', label: 'Kode', type: 'text', required: true },
            { name: 'name', label: 'Nama', type: 'text', required: true },
            { name: 'model', label: 'Model', type: 'text' },
            { name: 'categ', label: 'Kategori', type: 'text' },
            { name: 'maker_id', label: 'Maker', type: 'select', optionsFrom: { resource: 'makers', valueKey: 'id', labelKey: 'name' } },
            { name: 'line_id', label: 'Lintasan Produksi', type: 'select', optionsFrom: { resource: 'production-lines', valueKey: 'id', labelKey: 'name' } },
            { name: 'min_d', label: 'Diameter Min', type: 'number', step: '0.01' },
            { name: 'max_d', label: 'Diameter Max', type: 'number', step: '0.01' },
            { name: 'serial', label: 'Serial', type: 'text' },
            { name: 'kwh', label: 'kWh', type: 'number', step: '0.01' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },
    'contact-categories': {
        key: 'contact-categories',
        title: 'Contact Category',
        singular: 'Kategori Kontak',
        columns: [
            { key: 'id', label: 'ID' },
            { key: 'name', label: 'Nama' },
        ],
        fields: [{ name: 'name', label: 'Nama', type: 'text', required: true }],
    },
    contacts: {
        key: 'contacts',
        title: 'Contacts (Customer / Vendor)',
        singular: 'Kontak',
        columns: [
            { key: 'u_code', label: 'Kode' },
            { key: 'company_n', label: 'Perusahaan' },
            { key: 'nick_n', label: 'Nick' },
            { key: 'phone', label: 'Telepon' },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'u_code', label: 'Kode', type: 'text' },
            { name: 'company_n', label: 'Nama Perusahaan', type: 'text', required: true },
            { name: 'nick_n', label: 'Nama Panggilan', type: 'text' },
            { name: 'category_id', label: 'Kategori', type: 'select', optionsFrom: { resource: 'contact-categories', valueKey: 'id', labelKey: 'name' } },
            { name: 'address', label: 'Alamat', type: 'textarea', colSpan: 2 },
            { name: 'name', label: 'Contact Person', type: 'text' },
            { name: 'phone', label: 'Telepon', type: 'text' },
            { name: 'email', label: 'Email', type: 'text' },
            { name: 'identity', label: 'NPWP / ID', type: 'text' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },
    processes: {
        key: 'processes',
        title: 'Process',
        singular: 'Proses',
        columns: [
            { key: 'code', label: 'Kode' },
            { key: 'name_p', label: 'Nama Proses' },
            { key: 'descript', label: 'Deskripsi' },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'code', label: 'Kode', type: 'text', required: true },
            { name: 'name_p', label: 'Nama Proses', type: 'text', required: true },
            { name: 'descript', label: 'Deskripsi', type: 'textarea' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },
    racks: {
        key: 'racks',
        title: 'Master Rak',
        singular: 'Rak',
        columns: [
            { key: 'location', label: 'Lokasi' },
            { key: 'descriptions', label: 'Deskripsi' },
            { key: 'area', label: 'Area' },
            { key: 'rem_rack', label: 'Rak Remnant', render: (v) => (v ? 'Ya' : '') },
            { key: 'active', label: 'Aktif', render: (v) => (v ? 'Ya' : 'Tidak') },
        ],
        fields: [
            { name: 'location', label: 'Lokasi / Kode Rak', type: 'text', required: true },
            { name: 'descriptions', label: 'Deskripsi', type: 'text' },
            { name: 'area', label: 'Area (m²)', type: 'number', step: '0.01' },
            { name: 'height', label: 'Tinggi', type: 'number', step: '0.01' },
            { name: 'width', label: 'Lebar', type: 'number', step: '0.01' },
            { name: 'depth', label: 'Kedalaman', type: 'text' },
            { name: 'rem_rack', label: 'Rak Remnant (material sisa)', type: 'checkbox' },
            { name: 'active', label: 'Aktif', type: 'checkbox', default: true },
        ],
    },
    // NOTE: `items` is handled by its own tabbed screen (features/engineering/ItemPage.jsx),
    // not the generic CrudPage — see routing in app/App.jsx.
};
