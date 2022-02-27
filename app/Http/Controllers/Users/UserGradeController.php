<?php

namespace App\Http\Controllers\Users;

use App\Events\Notify;
use App\Events\UserUpdated;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Service\OperatorController;
use App\Http\Controllers\ServiceController;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class UserGradeController extends Controller
{
    /**
     * @param Request $request
     * @param int $id
     * @param int $userid
     * @return JsonResponse
     */
    public function setusergrade(Request $request, int $id, int $userid): JsonResponse
    {
        $user= User::where('id', $userid)->first();
        $requester = User::where('id', Auth::user()->id)->first();
        //TODO : Set un système de perm qui empèche de modif son propre grade sauf si DEV et ou ne peut pas changer un grade égal ou séprieur au siens

        /*
        if(true){
            if($user->id == $requester->id){
                event(new Notify('Impossible de modifier son propre grade ! ',4));
                return \response()->json(['status'=>'OK']);
            }
            if($id >= $requester->grade_id){
                event(new Notify('Impossible de mettre un grade plus haut que le siens ! ',4));
                return \response()->json(['status'=>'OK']);
            }
        }*/
        if(($user->medic_grade_id == 1  || $user->fire_grade_id == 1) && $id != 1){
            $users = User::whereNotNull('matricule')->get();
            $matricules = array();
            foreach ($users as $usere){
                array_push($matricules, $usere->matricule);
            }
            $generated = null;
            while(is_null($generated) || array_search($generated, $matricules)){
                $generated = random_int(10, 99);
            }
            $user->matricule = $generated;
            $user->save();
            event(new Notify($user->name . ' a le matricule ' . $generated,1));
        }
        if(Session::get('service')[0] === 'LSCoFD'){
            $user->fire_grade_id = $id;
        }
        if(Session::get('service')[0] === 'SAMS'){
            $user->medic_grade_id = $id;
        }
        $user->save();
        UserUpdated::broadcast($user);
        event(new Notify('Le grade a été bien changé ! ',1));
        return \response()->json(['status'=>'OK']);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function GetUserPerm(Request $request): JsonResponse
    {
        $user = User::where('id', Auth::id())->first();
        if($user->service === "SAMS"){
            $user->grade = $user->GetMedicGrade;
        }else if($user->service === "LSCoFD"){
            $user->grade = $user->GetFireGrade;
        }
        $user->GetMedicGrade;
        $user->GetFireGrade;
        return \response()->json(['status'=>'ok', 'user'=>$user]);
    }

    public function getGrade(): JsonResponse
    {
        $grades = Grade::where('service', Session::get('service')[0])->orderBy('power','desc')->get();
        $grades->filter(function ($item){
            return \Gate::allows('view', $item);
        });
        return \response()->json(['status'=>'OK','grades'=>$grades]);
    }

    public function createGrade(Request $request): JsonResponse
    {
        $grade = new Grade();
        $grade->name = 'nouveau grade';
        $grade->power = 0;
        $grade->service = Session::get('service')[0];
        $grade->save();

        return $this::getGrade();
    }

    public function updateGrade(Request $request){
        //TODO : PERM pour update + verif que le mec est admin si il set admin + verif la power du grade
        $grade = Grade::where('id', $request->grade['id'])->first();
        $exept = ['id', 'service', 'created_at','updated_at'];
        $updater = collect($request->grade)->except($exept);

        foreach ($updater  as $key => $value){
            $grade[$key] = $value;
        }
        $grade->save();
        Notify::dispatch('Mise à jour enregistrée',1,Auth::user()->id);
        return $this::getGrade();
    }

    public function postGrade(Request $request){

    }


    public function changePerm(string $perm, string $grade_id): JsonResponse
    {
        $grade = Grade::where('id', $grade_id)->first();
        $grade[$perm] = !$grade[$perm];
        $grade->save();
        event(new Notify('Vous avez changé une permissions',1));
        return \response()->json(['status'=>'OK'],201);
    }

    public static function removegradeFromuser(int $id){
        $user = User::where('id', $id)->first();
        $user->materiel = null;
        $user->matricule = null;
        $user->medic_grade_id = 1;
        $user->fire_grade_id = 1;
        $user->fire=false;
        $user->medic=false;
        $user->crossService= false;
        $user->bc_id = null;
        if($user->onService){
            OperatorController::setService($user, true);
        }
        $user->save();
        UserUpdated::broadcast($user);

        // mettre un embed de réinit du matériel

        Notify::dispatch($user->name .' ne fait plus partie du service',1, Auth::user()->id);
    }
}
