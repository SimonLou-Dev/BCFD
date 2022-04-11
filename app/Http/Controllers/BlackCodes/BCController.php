<?php

namespace App\Http\Controllers\BlackCodes;


use App\Enums\DiscordChannel;
use App\Events\BlackCodeListEdited;
use App\Events\BlackCodeUpdated;
use App\Events\Brodcaster;
use App\Events\Notify;
use App\Events\NotifyForAll;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\request\PrimesController;
use App\Jobs\ProcessEmbedPosting;
use App\Models\BCList;
use App\Models\BCPatient;
use App\Models\BCPersonnel;
use App\Models\BCType;
use App\Models\Blessure;
use App\Models\CouleurVetement;
use App\Models\Facture;
use App\Models\FireReport;
use App\Models\FireReportType;
use App\Models\Patient;
use App\Models\Rapport;
use App\Models\User;
use App\Exporter\ExelPrepareExporter;
use App\Jobs\ProcessEmbedBCGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use function GuzzleHttp\json_encode;


class BCController extends Controller
{

    public function getMainPage(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', BCList::class);
        $returned = null;
        $user = User::where('id', Auth::user()->id)->first();
        if(!is_null($user->bc_id)){
            $bc = BCList::where('id', $user->bc_id)->first();
            $returned = [
              'bc_id' => $bc->id,
              'service' => ($bc->service === 'LSCoFD' ? 'fire' : 'medic')
            ];
        }

        //Pagination for searh ended BC and forget
        $ActiveBcs = BCList::where('ended', false)->orderByDesc('id')->get();
        foreach ($ActiveBcs as $activeBc){
            $activeBc->GetType;
        }

        $queryPage = (int) $request->query('page');
        $readedPage = ($queryPage ?? 1) ;

        if(BCList::where('ended', true)->count() != 0){
            $searchedList = BCList::search($request->query('query'))->get()->reverse()->values();
            $forgetable = [];


            for($a = 0; $a < $searchedList->count(); $a++){
                $searchedItem = $searchedList[$a];
                if(!$searchedItem->ended){
                    array_push($forgetable, $a);
                }
                if(!\Gate::allows('view',$searchedItem)){
                    array_push($forgetable, $a);
                }
            }
            foreach ($forgetable as $forget){
                $searchedList->forget($forget);
            }

            foreach ($searchedList as $item) $item->GetType;

            $finalList = $searchedList->skip(($readedPage-1)*5)->take(5);
        }else{
            $finalList = collect([]);
            $searchedList = collect([]);
        }

        $url = $request->url() . '?query='.urlencode($request->query('query')).'&page=';
        $totalItem = $searchedList->count();
        $valueRounded = ceil($totalItem / 5);
        $maxPage = (int) ($valueRounded == 0 ? 1 : $valueRounded);
         //Creation of Paginate Searchable result
        $array = [
            'current_page'=>$readedPage,
            'last_page'=>$maxPage,
            'data'=> $finalList,
            'next_page_url' => ($readedPage === $maxPage ? null : $url.($readedPage+1)),
            'prev_page_url' => ($readedPage === 1 ? null : $url.($readedPage-1)),
            'total' => $totalItem,
        ];
        //End Pagination

        $types = BCType::all();
        return response()->json([
            "status"=>'OK',
            'active'=>$ActiveBcs,
            'ended'=>$array,
            'types'=>$types,
            'userBC'=>$returned,
        ]);
    }


    public function quitBc(Request $request){
        $user = User::where('id', Auth::user()->id)->first();
        if($user->bc_id != null){
            $logs = new LogsController();
            $logs->BCLogging('quit', $user->bc_id, $user->id);
            $user->bc_id = null;
            $user->save();
        }
        return response()->json([],201);
    }

    public function getBCByid(string $id): \Illuminate\Http\JsonResponse
    {


        $bc = BCList::where('id', $id)->first();
        $bc->GetType;
        $bc->GetUser;

        $bc->GetPersonnel;

        $bc->GetPatients;
        $user = User::where('id', Auth::user()->id)->first();
        if($bc->service === 'SAMS' && !$bc->ended && $user->bc_id != $bc->id){
            PersonnelController::addPersonel($bc->id, Auth::user()->id);
        }
        foreach ($bc->GetPatients as $patient){
            $patient->GetColor;
        }

        if($bc->ended){
            $blessures = Blessure::where('service', \Session::get('service')[0])->withTrashed()->get();
            $color= CouleurVetement::where('service', \Session::get('service')[0])->withTrashed()->get();
            $report = FireReportType::withTrashed()->get();
        }else{
            $blessures = Blessure::where('service', \Session::get('service')[0])->get();
            $color = CouleurVetement::where('service', \Session::get('service')[0])->get();
            $report = FireReportType::withoutTrashed()->get();
        }

        return response()->json([
            'status'=>'OK',
            'bc'=>$bc,
            'colors'=>$color,
            'blessures'=>$blessures,
            'fireType'=>$report
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addBc(Request $request): \Illuminate\Http\JsonResponse
    {

        $bcTypes = BCType::all();

        $request->validate([
            'type'=>['required','int','min:1'],
            'place'=>['required', 'string'],
        ]);
        $place = $request->place;
        $type = $request->type;
        $typeModel = BCType::where('id',$type)->first()->name;
        $fire = false;
        if(str_contains($typeModel, 'fire') || str_contains($typeModel, 'feux') || str_contains($typeModel, 'Incendie') || str_contains($typeModel, 'feux') || str_contains($typeModel, 'Fire')|| str_contains($typeModel, 'incendie')){
            $fire = true;
        }

        $bc = new BCList();
        $bc->starter_id = Auth::user()->id;
        $bc->place = $place;
        $bc->type_id = $type;
        $bc->service = ($fire ? 'LSCoFD' : 'SAMS');
        $bc->save();

        $logs = new LogsController();
        $logs->BCLogging('create', $bc->id, Auth::user()->id);

        PersonnelController::addPersonel($bc->id, Auth::user()->id);

        $embed = [
            [
                'title'=>'Black Code #' . $bc->id . ' en cours :',
                'fields'=>[
                    [
                        'name'=>'type :',
                        'value'=>$bc->GetType->name,
                        'inline'=>true,
                    ],
                    [
                        'name'=>'lieux :',
                        'value'=>$request->place,
                        'inline'=>true,
                    ]
                ],
                'color'=>'16775936',
                'footer'=> [
                    'text' => 'Information de : ' . Auth::user()->name
                ]
            ]
        ];

        \Discord::postMessage(DiscordChannel::BC, $embed, $bc);
        BlackCodeListEdited::dispatch();
        NotifyForAll::broadcast('Début du BC #'.$bc->id . ' à ' . $bc->place, 1);

        return response()->json([
            'status'=>'OK',
            'endUrl'=>($fire ? 'fire' : 'medic')."/".$bc->id,
        ],201);
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function endBc(int $id=9): \Illuminate\Http\JsonResponse
    {
        $this->authorize('close', BCList::class);


        $bc = BCList::where('id', $id)->firstOrFail();
        if($bc->service== 'LSCoFD') return response()->json([],404);

        self::closBlackCode($bc);


        return response()->json(['status'=>'OK'],202);
    }

    public static function closBlackCode($bc){
        $bc->ended = true;
        $bc->save();
        $users = User::where('bc_id', $bc->id)->get();
        $personnels = $bc->GetPersonnel;
        foreach ($personnels as $personnel){
            PrimesController::AddValidPrimesToUser($personnel->user_id, 1);
        }
        $patients = $bc->GetPatients;

        $start = date_create($bc->created_at);
        $end = new \DateTime();
        $interval = $start->diff($end);
        $formated = $interval->format('%H h %I min(s)');
        ProcessEmbedBCGenerator::dispatch($formated, $bc, Auth::user()->name, \Discord::chanGet(DiscordChannel::BC));
        BlackCodeListEdited::dispatch();
        BlackCodeUpdated::dispatch($bc->id);
        NotifyForAll::broadcast('Fin du BC #'.$bc->id . ' à ' . $bc->place, 1);
        foreach ($users as $user){
            $user->bc_id = null;
            $user->save();
        }

        $logs = new LogsController();
        $logs->BCLogging('close', $bc->id, Auth::user()->id);
    }

    /**
     * @param string $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function generateRapport(string $id): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $bc = BCList::where('id',$id)->first();
        $columns[] = ['prénom nom', 'couleur vêtement', 'date et heure d\'ajout'];
        foreach ($bc->GetPatients as $patient){
            $columns[] = [
                $patient->GetPatient->vorname . ' ' . $patient->GetPatient->name,
                CouleurVetement::withTrashed()->where('id', $patient->couleur)->first()->name,
                date('d/m/Y H:i', strtotime($patient->created_at))
            ];
        }

        $export = new ExelPrepareExporter($columns);
        return Excel::download((object)$export, 'ListePatientsBc'. $id .'.xlsx');
    } #a refaire

    public function casernePatcher (Request $request, string $id){
        $this->authorize('update', BCList::class);
        $bc = BCList::where('id', $id)->first();
        $bc->caserne = $request->caserne;
        $bc->save();

        $logs = new LogsController();
        $logs->BCLogging('update caserne', $bc->id, Auth::user()->id);

        Notify::dispatch('Mise à jour effectuée',1,Auth::user()->id);
        BlackCodeUpdated::dispatch($bc->id);

        return response()->json([
            'status'=>'OK'
        ], 204);
    }

    public function descPatcher (Request $request, string $id){
        $this->authorize('update', BCList::class);
        $bc = BCList::where('id', $id)->first();
        $bc->description = $request->description;
        $bc->save();

        $logs = new LogsController();
        $logs->BCLogging('update desc', $bc->id, Auth::user()->id);

        Notify::dispatch('Mise à jour effectuée',1,Auth::user()->id);
        BlackCodeUpdated::dispatch($bc->id);

        return response()->json([
            'status'=>'OK'
        ], 204);
    }

    public function infosPatcher(Request $request, string $id){
        $this->authorize('update', BCList::class);
        $bc = BCList::where('id', $id)->first();
        $bc->caserne = $request->caserne;
        $bc->place = $request->place;
        $bc->save();

        $logs = new LogsController();
        $logs->BCLogging('update infos', $bc->id, Auth::user()->id);

        Notify::dispatch('Mise à jour effectuée',1,Auth::user()->id);
        BlackCodeUpdated::dispatch($bc->id);

        return response()->json([
            'status'=>'OK'
        ], 204);
    }

    public function generatePDF(string $id){
        $bc = BCList::where('id',$id)->first();
        $pdf = Pdf::loadView('pdf.BC',['bc'=>$bc]);

        return $pdf->stream();
    }

}
