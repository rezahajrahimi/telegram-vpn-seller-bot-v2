<?php
namespace App\Http\Controllers;

use App\Models\AdvanceSettingLookup;
use Illuminate\Http\Request;

class AdvanceSettingLookupController extends Controller
{
    public function getAll()
    {
        try {
            $advanceSettingLookups = AdvanceSettingLookup::all();
            if ($advanceSettingLookups->isEmpty()) {
                $this->seed();
                $advanceSettingLookups = AdvanceSettingLookup::all();
            }
            return response()->json($advanceSettingLookups);
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getAll->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }
    public function getAllWithBooleanValue()
    {
        try {
            $advanceSettingLookups = AdvanceSettingLookup::all()->map(function ($item) {
                return ['name' => $item->name, 'value' => $item->booleanValue];
            });
            if ($advanceSettingLookups->isEmpty()) {
                $this->seed();
                $advanceSettingLookups = AdvanceSettingLookup::all()->map(function ($item) {
                    return ['name' => $item->name, 'value' => $item->booleanValue];
                });
            }
            return $advanceSettingLookups;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getAllWithBooleanValue->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }
    public function seed()
    {
        $advanceSettingLookups = [
            ['name' => 'bot_show_configs_by_panels_category', 'value' => 'false', 'description' => 'نمایش کانفیگ ها براساس موقیت جغرافیایی پنل'],
            ['name' => 'bot_auto_set_price_by_dollar_price', 'value' => 'false', 'description' => 'قیمت گذاری اتوماتیک بر اساس قیمت دلار'],
            ['name' => 'bot_calculate_product_category_price_in_dollar_by_toman', 'value' => 'false', 'description' => 'قیمت گذاری اتوماتیک بر اساس قیمت تومان'],
            ['name' => 'bot_show_one_row_config', 'value' => 'true', 'description' => 'نمایش پیکربندی ها در یک ردیف'],
            ['name' => 'bot_daily_backup', 'value' => 'true', 'description' => 'برای ایجاد بکاپ روزانه'],
            ['name' => 'bot_auto_delete_expired_configs', 'value' => 'true', 'description' => 'حذف کانفیگ هایی که 10 روز از انقضا آنها می گذرد'],
        ];
        AdvanceSettingLookup::insert($advanceSettingLookups);
    }
    public function re_seed_advance_settings_lookup(){
        try{
            // truncate all date and run seed
            AdvanceSettingLookup::truncate();
            $this->seed();
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->re_seed_advance_settings_lookup->error", ['error' => $th->getMessage()]);
            return response()->json('Server Error', 500);
        }
    }
    public function getByName($name)
    {
        try {
            return AdvanceSettingLookup::getByName($name);
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getByName->error", ['error' => $th->getMessage(), 'name' => $name]);
            return null;
        }
    }
    public function getByNameWithBooleanValue($name)
    {
        try {
            return AdvanceSettingLookup::getByName($name)->booleanValue;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getByName->error", ['error' => $th->getMessage(), 'name' => $name]);
            return null;
        }
    }
    public function getByNameAndValue($name, $value)
    {
        try {
            return AdvanceSettingLookup::getByNameAndValue($name, $value);
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getByNameAndValue->error", ['error' => $th->getMessage(), 'name' => $name, 'value' => $value]);
            return null;
        }
    }
    public function getValueByNameWithBooleanValue($name)
    {
        try {
            $advanceSettingLookup = AdvanceSettingLookup::getByName($name);
            if ($advanceSettingLookup == null) {
                // get all advance setting lookups, then clear all of them, then run $this->seed function, update new ones with old values, then get the advance setting lookup by name
                $advanceSettingLookups = AdvanceSettingLookup::all();
                AdvanceSettingLookup::truncate();
                $this->seed();
                foreach ($advanceSettingLookups as $advanceSettingLookup) {
                    $this->update(Request::create($advanceSettingLookup->id, $advanceSettingLookup->name, $advanceSettingLookup->value, $advanceSettingLookup->description));
                }
                $advanceSettingLookup = AdvanceSettingLookup::getByName($name);
                return $advanceSettingLookup->booleanValue;
            }
            if ($advanceSettingLookup->booleanValue == 'true') {
                return true;
            }
            return false;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->getByNameAndValue->error", ['error' => $th->getMessage(), 'name' => $name, 'value' => $value]);
            return null;
        }
    }

    /**
     * Create a new advance setting lookup.
     *
     * @param string $name the name of advance setting lookup
     * @param string $value the value of advance setting lookup
     * @param string|null $description the description of advance setting lookup
     *
     * @return \App\Models\AdvanceSettingLookup
     */
    public function create($name, $value, $description = null)
    {
        try {
            return AdvanceSettingLookup::create(['name' => $name, 'value' => $value, 'description' => $description]);
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->create->error", ['error' => $th->getMessage(), 'name' => $name, 'value' => $value, 'description' => $description]);
            return null;
        }
    }
    public function update(Request $request)
    {
        try {
            $advanceSettingLookup              = AdvanceSettingLookup::find($request->id);
            $advanceSettingLookup->name        = $request->name;
            $advanceSettingLookup->value       = $request->value;
            $advanceSettingLookup->description = $request->description;
            $advanceSettingLookup->update();
            return $advanceSettingLookup;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->update->error", ['error' => $th->getMessage(), 'id' => $id, 'name' => $name, 'value' => $value, 'description' => $description]);
            return null;
        }
    }
    public function updateByName(Request $request)
    {
        try {
            $advanceSettingLookup              = AdvanceSettingLookup::where('name', $request->name)->first();
            $advanceSettingLookup->value       = $request->value;
            $advanceSettingLookup->description = $request->description;
            $advanceSettingLookup->update();
            return $advanceSettingLookup;
        } catch (\Throwable $th) {
            \Log::info("AdvanceSettingLookupController->update->error", ['error' => $th->getMessage(), 'id' => $id, 'name' => $name, 'value' => $value, 'description' => $description]);
            return null;
        }
    }

}
