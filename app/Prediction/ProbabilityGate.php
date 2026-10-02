<?php
namespace App\Prediction;
final class ProbabilityGate {
    public function eligible(array $prediction,string $requiredVersion,string $snapshotHash): array {
        if (($prediction['model_version']??null)!==$requiredVersion || ($prediction['snapshot_hash']??null)!==$snapshotHash)
            return ['eligible'=>false,'reason'=>'version_or_snapshot_mismatch'];
        if (($prediction['calibration_approved']??false)!==true) return ['eligible'=>false,'reason'=>'uncalibrated'];
        $p=$prediction['probability']??null;
        if (!is_numeric($p) || !is_finite((float)$p) || $p<0 || $p>1) return ['eligible'=>false,'reason'=>'invalid_probability'];
        return ['eligible'=>true,'reason'=>'valid','probability'=>(float)$p];
    }
}
