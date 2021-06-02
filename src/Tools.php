<?php

    namespace b2db;

    /**
     * Tools class for common tool methods
     *
     * @package b2db
     */
    class Tools
    {

        /**
         * @param array<string, mixed> $aArray1
         * @param array<string, mixed> $aArray2
         * @noinspection TypeUnsafeComparisonInspection
         *
         * @return array<string, mixed>
         */
        public static function array_diff_recursive(array $aArray1, array $aArray2): array
        {
            $aReturn = [];

            foreach ($aArray1 as $mKey => $mValue) {
                if (array_key_exists($mKey, $aArray2)) {
                    if (is_array($mValue)) {
                        $aRecursiveDiff = self::array_diff_recursive($mValue, $aArray2[$mKey]);
                        if (count($aRecursiveDiff)) {
                            $aReturn[$mKey] = $aRecursiveDiff;
                        }
                    } elseif ($mValue != $aArray2[$mKey]) {
                        $aReturn[$mKey] = $mValue;
                    }
                } else {
                    $aReturn[$mKey] = $mValue;
                }
            }
            return $aReturn;
        }

    }
