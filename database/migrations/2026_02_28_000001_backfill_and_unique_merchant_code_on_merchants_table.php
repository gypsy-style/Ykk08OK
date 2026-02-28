<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 既存の重複を解消（同じmerchant_codeが複数あるとUNIQUEが張れない）
        $duplicates = DB::table('merchants')
            ->select('merchant_code', DB::raw('COUNT(*) as c'))
            ->whereNotNull('merchant_code')
            ->where('merchant_code', '<>', '')
            ->groupBy('merchant_code')
            ->having('c', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $ids = DB::table('merchants')
                ->where('merchant_code', $dup->merchant_code)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            // 先頭は残し、2件目以降だけ再発行
            array_shift($ids);
            foreach ($ids as $id) {
                DB::table('merchants')->where('id', $id)->update([
                    'merchant_code' => $this->generateUniqueCode(),
                ]);
            }
        }

        // NULL/空文字を埋める
        $nullOrEmptyIds = DB::table('merchants')
            ->whereNull('merchant_code')
            ->orWhere('merchant_code', '=', '')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        foreach ($nullOrEmptyIds as $id) {
            DB::table('merchants')->where('id', $id)->update([
                'merchant_code' => $this->generateUniqueCode(),
            ]);
        }

        Schema::table('merchants', function (Blueprint $table) {
            $table->unique('merchant_code');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropUnique('merchants_merchant_code_unique');
        });
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'GOON-' . Str::upper(Str::random(4));
        } while (DB::table('merchants')->where('merchant_code', $code)->exists());

        return $code;
    }
};

