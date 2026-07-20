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
