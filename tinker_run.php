
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// 1. Cari tabel kurs
$tables = DB::select('SHOW TABLES LIKE "%exchange_rate%"');
$tblName = "";
if(count($tables) > 0) {
    foreach($tables[0] as $k => $v) { $tblName = $v; break; }
}

if(!$tblName) {
    // Kalau nggak ada, sekalian kita build yang proper
    Schema::create('m_exchange_rates', function (Blueprint $table) {
        $table->id();
        $table->string('currency_code', 3); // USD, EUR
        $table->string('base_currency', 3)->default('IDR');
        $table->string('rate_code', 20)->default('BI'); // BI, PAJAK, BUDGET
        $table->date('effective_date');
        $table->decimal('exchange_rate', 15, 4);
        $table->timestamps();
    });
    echo "Tabel m_exchange_rates dibuat baru lengkap dengan rate_code.";
} else {
    // Inject kalau tabelnya sudah ada
    if (!Schema::hasColumn($tblName, 'rate_code')) {
        Schema::table($tblName, function (Blueprint $table) {
            $table->string('rate_code', 20)->default('BI')->after('currency_code')->comment('BI, PAJAK, BUDGET, HEDGING');
        });
        echo "Kolom rate_code sukses ditambahkan ke \$tblName.";
    } else {
        echo "Kolom rate_code sudah ada.";
    }
}
