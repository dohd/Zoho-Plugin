<?php

use App\Models\medical_insurers\MedicalInsurer;
use App\Models\medical_insurers\MedicalPlan;
use App\Models\medical_insurers\OptionRate;
use App\Models\medical_insurers\PlanBenefit;
use App\Models\medical_insurers\PlanOption;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required',
        'password' => 'required',
    ]);
    $user = User::where('email', $request->email)->first();
    if (!$user || !Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
    }
    
    return response()->json(['access_token' => $user->createToken(config('app.name'))->plainTextToken]);
});

Route::group(['middleware' => 'auth:sanctum'], function() {
    // medical insurers
    Route::get('medical_insurers', function(Request $request) {
        $medical_insurers = MedicalInsurer::get();
        return response()->json($medical_insurers);
    });
    Route::get('medical_insurers/{medical_insurer}', function(MedicalInsurer $medical_insurer) {
        $medical_insurer['plans'] = $medical_insurer->plans()
        ->with('plan_options', 'shared_rates')
        ->with(['option_rates' => fn($q) => $q->with('rate_variables')->get()])
        ->with('plan_benefits')
        ->get();

        return response()->json($medical_insurer);
    });
    
    // medical plans
    Route::get('medical_plans', function(Request $request) {
        $input = $request->only('insurer_id');
        $medical_plans = MedicalPlan::where($input)->get();
        return response()->json($medical_plans);
    });

    // plan benefits
    Route::get('plan_benefits', function(Request $request) {
        $input = $request->only('plan_id');
        $plan_benefits = PlanBenefit::where($input)->get();
        return response()->json($plan_benefits);
    });
    // plan options
    Route::post('plan_options', function(Request $request) {
        $input = array_filter($request->only('insurer_id', 'plan_id', 'class'));
        $plan_options = PlanOption::where($input)->get();
        return response()->json($plan_options);
    });
    // option rates
    Route::post('option_rates', function(Request $request) {
        $input = array_filter($request->only('insurer_id', 'plan_id', 'class'));
        $option_rates = OptionRate::where($input)->with('rate_variables')->get();
        return response()->json($option_rates);
    });

    // quote details
    Route::post('quote_details', function(Request $request) {
        $input = $request->all();
        $validator = Validator::make($input, [
            'principal_age' => request('spouse_age')? '' : 'required',
            'spouse_age' => request('principal_age')? '' : 'required',
            'limit' => 'required',
        ]);
        if ($validator->fails()) return response()->json($validator->messages());

        $results = [];
        $medical_plans = MedicalPlan::get();
        foreach ($medical_plans as $key => $medical_plan) {
            $plan_result = [
                'underwriter' => @$medical_plan->medical_insurer->name,
                'medical_plan' => $medical_plan->plan_name,
                'limit' => $input['limit'],
                'plan_options' => [],
            ];
            
            $principal_age = $input['principal_age'];
            if ($input['spouse_age'] > $input['principal_age']) $principal_age = $input['spouse_age'];

            $plan_options = $medical_plan->plan_options()->where('limit', $input['limit'])->get();
            foreach ($plan_options as $plan_option) {
                $option_result = [
                    'plan_option_id' => $plan_option->id,
                    'inpatient_id' => $plan_option->inpatient_id,
                    'class' => $plan_option->class,
                    'label' => $plan_option->label,
                    'shared' => [],
                    'per_person' => [],
                ];
                if (!$option_result['label'] && $option_result['inpatient_id']) {
                    $parent_opt = $medical_plan->plan_options()->where('medical_plan_options.id', $option_result['inpatient_id'])->first();
                    $option_result['label'] = @$parent_opt->label;
                } 
                /**
                 * Per person
                 */
                $principal_opt_rate = $medical_plan->option_rates()
                    ->where('limit_label', 'Principal')
                    ->where('age_from', '<=', $principal_age)
                    ->where('age_to', '>=', $principal_age)
                    ->where('class', $plan_option->class)
                    ->first();
                if ($input['principal_age'] && $input['spouse_age'] && $principal_opt_rate) {
                    $spouse_opt_rate = $medical_plan->option_rates()
                        ->where('limit_label', 'Spouse')
                        ->where('class', $plan_option->class)
                        ->where(function($q) use($principal_opt_rate) {
                            $q->where('row_index', $principal_opt_rate->row_index+1)
                            ->orWhere('row_index', $principal_opt_rate->row_index+2);
                        })
                        ->first();
                }
                if ($input['children_count'] && $principal_opt_rate) {
                    $child_opt_rate = $medical_plan->option_rates()
                        ->where('limit_label', 'Child')
                        ->where('class', $plan_option->class)
                        ->where(function($q) use($principal_opt_rate) {
                            $q->where('row_index', $principal_opt_rate->row_index+1)
                            ->orWhere('row_index', $principal_opt_rate->row_index+2);
                        })
                        ->first();
                }
                
                $principal_rate_var = @$principal_opt_rate->rate_variables? $principal_opt_rate->rate_variables->where('plan_option_id', $plan_option->id)->first() : null;
                $spouse_rate_var = @$spouse_opt_rate->rate_variables? $spouse_opt_rate->rate_variables->where('plan_option_id', $plan_option->id)->first() : null;
                $child_rate_var = @$child_opt_rate->rate_variables? $child_opt_rate->rate_variables->where('plan_option_id', $plan_option->id)->first() : null;
                $per_person_data = [
                    'principal_premium' => +@$principal_rate_var->rate,
                    'spouse_premium' => +@$spouse_rate_var->rate,
                    'child_premium' => +@$child_rate_var->rate * $input['children_count'],
                ];
                $per_person_data['premium'] = $per_person_data['principal_premium'] + $per_person_data['spouse_premium'] + $per_person_data['child_premium'];
                $option_result['per_person'] = $per_person_data;
    
                /**
                 * shared
                 */
                $principal_shared_rate = $medical_plan->shared_rates()
                    ->where('class', $plan_option->class)
                    ->where('label', $option_result['label'])
                    ->where('age_from', '<=', $principal_age)
                    ->where('age_to', '>=', $principal_age)
                    ->first();
    
                if ($input['principal_age'] && $input['spouse_age'] && $input['children_count']) {
                    $n = 1 + $input['children_count'];
                    $principal_rate = @$principal_shared_rate['m'.$n];
                } elseif (($input['principal_age'] || $input['spouse_age']) && $input['children_count']) {
                    $n = $input['children_count'];
                    $principal_rate = @$principal_shared_rate['m'.$n];
                } elseif ($input['principal_age'] || $input['spouse_age']) {
                    $principal_rate = @$principal_shared_rate['m'];
                }
                $shared_data = [
                    'principal_premium' => +$principal_rate,
                    'premium' => +$principal_rate,
                ];
                $option_result['shared'] = $shared_data;

                if (!$option_result['per_person']['principal_premium'] && !$option_result['shared']['principal_premium']) {
                    continue;
                }
                $plan_result['plan_options'][] = $option_result;
            }    
            if (!$plan_result['plan_options']) continue;
            $results[] = $plan_result;
        }

        return response()->json($results);
    });
});
