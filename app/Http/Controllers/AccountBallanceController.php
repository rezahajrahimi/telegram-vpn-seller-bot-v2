<?php
namespace App\Http\Controllers;

use App\Http\Controllers\AgentPermissonController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\TransactionSettingController;
use App\Models\AccountBallance;
use App\Models\AgentPermisson;
use App\Models\BotUser;
use App\Models\User;
use Illuminate\Http\Request;

class AccountBallanceController extends Controller
{
    public function checkUserHasBalance($userID, $price, $parice_in_dollar)
    {
        try {
            // for test account
            if ($price == 0 && $parice_in_dollar == 0) {
                return true;
            }
            // get user
            $user = User::where('account_id', $userID)->first();
            if ($user == null) {
                return false;
            }
            // check user is admin
            if ($user->role == 'admin') {
                return true;
            }
            // check agent
            if ($user->role == 'agent') {
                $agentPremissionCntrl = new AgentPermissonController();
                $agentPr              = $agentPremissionCntrl->getUserPremission();
                if ($agentPr != null) {
                    if ($agentPr->minus_ballance === 1 || $agentPr->minus_ballance === true) {

                        return true;
                    }
                }
            }

            // common product categorey check
            $data = AccountBallance::where('account_id', $userID)->first();

            if ($data != null) {
                if ($data->ballance >= $price) {
                    return true;
                } elseif ($data->account_ballance_in_dollar >= $parice_in_dollar) {
                    if ($this->checkDollarPay() == true || $this->checkDollarPay() == 1 && $parice_in_dollar > 0) {
                        return true;
                    }
                    return false;
                }
                return false;
            } else {
                $newAcc                             = new AccountBallance();
                $newAcc->account_id                 = $userID;
                $newAcc->ballance                   = 0;
                $newAcc->account_ballance_in_dollar = 0;
                $newAcc->save();
                return false;
            }
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return false;
        }
    }
    public function getUserAccuntBalance($userID)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            return $data->ballance;
        } else {
            $newAcc                             = new AccountBallance();
            $newAcc->account_id                 = $userID;
            $newAcc->ballance                   = 0;
            $newAcc->account_ballance_in_dollar = 0;
            $newAcc->save();

            return 0;
        }
    }
    public function getUserAccuntBalanceInDollar($userID)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            return $data->account_ballance_in_dollar;
        } else {
            $newAcc                             = new AccountBallance();
            $newAcc->account_id                 = $userID;
            $newAcc->ballance                   = 0;
            $newAcc->account_ballance_in_dollar = 0;
            $newAcc->save();

            return 0;
        }
    }
    public function incUserAccuntBalance($userID, $ballance)
    {
        try {
            $data = AccountBallance::where('account_id', $userID)->first();
            if ($data != null) {
                $data->ballance += $ballance;

                $data->update();
                return true;
            } else {
                $newAcc                             = new AccountBallance();
                $newAcc->account_id                 = $userID;
                $newAcc->ballance                   = $ballance;
                $newAcc->account_ballance_in_dollar = 0;
                $newAcc->save();

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return false;
        }
    }
    public function increaseUserAccuntBalanceByUserID(Request $request)
    {
        try {
            $user = BotUser::where('id', $request->userID)->first();

            if ($user != null) {
                $userAccountID = $user->account_id;
                $ballance      = $request->ballance;
                $type          = $request->type;

                if ($type == 'toman') {
                    $this->incUserAccuntBalance($userAccountID, $ballance);

                    $logCtrl = new LogController();
                    $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' تومان افزایش یافت', $userAccountID, '', 'edit');
                    return $this->getUserAccuntBalance($userAccountID);
                } else {
                    $this->incUserAccuntBalanceInDollar($userAccountID, $ballance);
                    $logCtrl = new LogController();

                    $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' دلار افزایش یافت', $userAccountID, '', 'edit');
                    return $this->getUserAccuntBalanceInDollar($userAccountID);
                }
            }
            return response()->json(null, 404);
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return response()->json(null, 500);
        }
    }
    public function decreaseUserAccuntBalanceByUserID(Request $request)
    {
        try {
            $user                      = BotUser::where('id', $request->userID)->first();
            $is_admin                  = false;
            $is_agent                  = false;
            $minus_ballance_permission = false;
            $minus_ballance_permission       = $request->is_request_by_admin ?? false;
            if ($user == null) {
                $user = BotUser::where('account_id', $request->userID)->first();
                if ($user == null) {
                    return false;
                }
            }
            $user_role = User::where('account_id', $user->account_id)->first();
            if ($user_role != null) {
                if ($user_role->role == 'admin') {
                    $is_admin = true;
                }
                if ($user_role->role == 'agent') {
                    $is_agent         = true;
                    $agent_permission = AgentPermisson::where('account_id', $request->userID)->first();
                    if ($agent_permission != null) {
                        if ($agent_permission->minus_ballance == 1 || $agent_permission->minus_ballance == true) {
                            $minus_ballance_permission = true;
                        }
                    }
                }
            } else {
                return false;
            }

            $userAccountID = $user->account_id;
            $ballance      = $request->ballance;
            $type          = $request->type;
            $accBallance   = AccountBallance::where('account_id', $userAccountID)->first();
            if ($accBallance == null) {
                $newAcc                             = new AccountBallance();
                $newAcc->account_id                 = $request->userID;
                $newAcc->ballance                   = 0;
                $newAcc->account_ballance_in_dollar = 0;
                $newAcc->save();
                if ($is_admin) {
                    return true;
                }
                return false;
            }

            if ($type == 'toman') {
                \Log::info("type is toman");
                if ($ballance <= $accBallance->ballance) {
                    $accBallance->ballance -= $ballance;
                    $accBallance->update();
                    $logCtrl = new LogController();
                    $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' تومان کاهش یافت', $userAccountID, '', 'edit');
                    return $accBallance->ballance;
                } else {
                    if ($is_admin || $minus_ballance_permission) {
                        $accBallance->ballance -= $ballance;
                        $accBallance->update();
                        $logCtrl = new LogController();
                        $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' تومان کاهش یافت', $userAccountID, '', 'edit');
                        return $accBallance->ballance;
                    }
                    return false;
                }
            } elseif ($type == 'dollar') {
                if ($ballance <= $accBallance->account_ballance_in_dollar) {
                    $accBallance->account_ballance_in_dollar -= doubleval($ballance);
                    $accBallance->update();
                    $logCtrl = new LogController();
                    $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' دلار کاهش یافت', $userAccountID, '', 'edit');
                    return $accBallance->account_ballance_in_dollar;
                } else {
                    if ($is_admin || $minus_ballance_permission) {
                        $accBallance->account_ballance_in_dollar -= doubleval($ballance);
                        $accBallance->update();
                        $logCtrl = new LogController();
                        $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر به مقدار ' . $request->ballance . ' دلار کاهش یافت', $userAccountID, '', 'edit');
                        return $accBallance->account_ballance_in_dollar;
                    }
                    return false;
                }
            }
            return false;
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return response()->json(null, 500);
        }
    }
    public function incUserAccuntBalanceInDollar($userID, $ballance)
    {
        try {
            $data = AccountBallance::where('account_id', $userID)->first();
            if ($data != null) {
                $data->account_ballance_in_dollar += $ballance;

                $data->update();
                return true;
            } else {
                $newAcc                             = new AccountBallance();
                $newAcc->account_id                 = $userID;
                $newAcc->account_ballance_in_dollar = $ballance;
                $newAcc->ballance                   = 0;
                $newAcc->save();

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("message $th");
            return false;
        }
    }
    public function decUserAccuntBalance($userID, $ballance, $parice_in_dollar)
    {
        $data = AccountBallance::where('account_id', $userID)->first();
        if ($data != null) {
            if ($data->ballance >= $ballance) {
                $data->ballance -= $ballance;
                $data->update();

                return true;
            } elseif ($data->account_ballance_in_dollar >= $parice_in_dollar) {
                $data->account_ballance_in_dollar -= doubleval($parice_in_dollar);
                $data->update();
                return true;
            } else {
                $agentPremissionCntrl = new AgentPermissonController();
                $agentPr              = $agentPremissionCntrl->getUserPremission();
                if ($agentPr->minus_ballance == 1 || $agentPr->minus_ballance == true) {
                    $data->ballance -= $ballance;
                    $data->update();
                    \Log::info("  $data->ballance");
                    return true;
                }
                return false;
            }
        } else {
            return false;
        }
    }
    public function setNewAccountBallance(Request $request)
    {
        try {
            $data = AccountBallance::where('account_id', $request->userID)->first();
            if ($data != null) {
                $data->ballance = $request->ballance;

                $data->update();

                $logCtrl = new LogController();
                $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر بصورت دستی به ' . $request->ballance . ' تومان تغییر کرد', $request->userID, '', 'edit');

                return true;
            } else {
                $newAcc             = new AccountBallance();
                $newAcc->account_id = $request->userID;
                $newAcc->ballance   = $request->ballance;
                $newAcc->save();
                $logCtrl->addNewLog('ballance', 'میزان موجودی کاربر بصورت دستی به ' . $request->ballance . ' تومان تغییر کرد', $request->userID, '', 'edit');

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    public function setNewDollarAccountBallance(Request $request)
    {
        try {
            $data = AccountBallance::where('account_id', $request->userID)->first();
            if ($data != null) {
                $data->account_ballance_in_dollar = $request->ballance;

                $data->update();

                $logCtrl = new LogController();
                $logCtrl->addNewLog('ballance', 'میزان موجودی دلاری کاربر بصورت دستی به ' . $request->ballance . ' دلار تغییر کرد', $request->userID, '', 'edit');

                return true;
            } else {
                $newAcc                             = new AccountBallance();
                $newAcc->account_id                 = $request->userID;
                $newAcc->account_ballance_in_dollar = $request->ballance;
                $newAcc->save();
                $logCtrl->addNewLog('ballance', 'میزان موجودی دلاری کاربر بصورت دستی به ' . $request->ballance . ' دلار تغییر کرد', $request->userID, '', 'edit');

                return true;
            }
        } catch (\Throwable $th) {
            \Log::info("Throwable $th");
            return response()->json('Server Error', 500);
        }
    }
    /// Agent Functions
    public function getLoggedUserBallancce($account_id = null)
    {
        try {
            $userId = null;
            if ($account_id == null) {
                $userId = auth('sanctum')->user()->account_id;
            } else {
                $userId = $account_id;
            }
            $data = AccountBallance::where('account_id', $userId)->first();
            if (! $data) {
                $newAcc                             = new AccountBallance();
                $newAcc->account_id                 = $userId;
                $newAcc->account_ballance_in_dollar = 0;
                $newAcc->ballance                   = 0;
                $newAcc->save();
                return $newAcc;
            }
            return $data;
        } catch (\Throwable $th) {
            \Log::info("$th");
            return response()->json(false, 500);
        }
    }
    /// check  dollarPay is valid or not
    public function checkDollarPay()
    {
        $trSettingCntrl = new TransactionSettingController();

        return $trSettingCntrl->getDollorTransactionSetting();
    }
}
