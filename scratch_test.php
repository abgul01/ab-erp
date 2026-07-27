<?php
use App\Models\User;
use App\Models\prd_mps;
use App\Http\Controllers\Api\Production\MpsController;
use App\Http\Controllers\Api\Production\MppController;

$admin = User::where('username','admin')->first();
$period = now()->format('Ym');

// MPS generate
$req = Illuminate\Http\Request::create('/api/v1/mps/generate','POST',['period'=>$period]);
$req->setUserResolver(fn()=>$admin);
$before = prd_mps::where('status','DRAFT')->count();
$resp = app(MpsController::class)->generate($req);
echo "MPS generate: ".$resp->getContent().PHP_EOL;
echo "DRAFT MPS before=$before after=".prd_mps::where('status','DRAFT')->count().PHP_EOL;

// MPP generate 3 months
$periods = [now()->format('Ym'), now()->copy()->addMonth()->format('Ym'), now()->copy()->addMonths(2)->format('Ym')];
$req2 = Illuminate\Http\Request::create('/api/v1/mpp/generate','POST',['periods'=>$periods,'source'=>'MAX']);
$req2->setUserResolver(fn()=>$admin);
$resp2 = app(MppController::class)->generate($req2);
echo "MPP generate: ".$resp2->getContent().PHP_EOL;
