"""Standalone offline model research. Never connects to exchange or accepts order instructions."""
from __future__ import annotations
import hashlib
import json
from dataclasses import dataclass
from pathlib import Path
import numpy as np
import pandas as pd
from sklearn.calibration import CalibratedClassifierCV
from sklearn.dummy import DummyClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import brier_score_loss
from sklearn.model_selection import TimeSeriesSplit

FEATURES = ("rsi14", "relative_volume", "atr_pct", "funding_rate", "open_interest_change")
LABEL = "net_profitable_closed_trade"

@dataclass(frozen=True)
class Experiment:
    source_hash: str
    training_count: int
    validation_count: int
    baseline_brier: float
    candidate_brier: float
    eligible_for_execution: bool = False

def evaluate_csv(path: str) -> Experiment:
    raw = Path(path).read_bytes()
    data = pd.read_csv(path).sort_values("entry_time", kind="stable")
    required = set(FEATURES) | {LABEL, "entry_time", "exit_time"}
    missing = required - set(data.columns)
    if missing:
        raise ValueError(f"Missing required columns: {sorted(missing)}")
    if data[list(FEATURES)].isna().any().any():
        raise ValueError("Missing features must not be fabricated")
    if len(data) < 200:
        raise ValueError("At least 200 completed labeled trades required")
    split = int(len(data) * 0.8)
    train, holdout = data.iloc[:split], data.iloc[split:]
    if pd.to_datetime(train.exit_time).max() >= pd.to_datetime(holdout.entry_time).min():
        raise ValueError("Train/holdout label overlap; purge overlapping trades first")
    if train[LABEL].nunique() < 2:
        raise ValueError("Training requires both outcome classes")
    x_train, y_train = train[list(FEATURES)], train[LABEL].astype(int)
    x_holdout, y_holdout = holdout[list(FEATURES)], holdout[LABEL].astype(int)
    baseline = DummyClassifier(strategy="prior").fit(x_train, y_train)
    # Time-ordered folds; add explicit embargo/purging before claiming independent validation.
    candidate = CalibratedClassifierCV(
        LogisticRegression(max_iter=2000, class_weight="balanced"),
        cv=TimeSeriesSplit(n_splits=3), method="sigmoid"
    ).fit(x_train, y_train)
    baseline_brier = brier_score_loss(y_holdout, baseline.predict_proba(x_holdout)[:, 1])
    candidate_brier = brier_score_loss(y_holdout, candidate.predict_proba(x_holdout)[:, 1])
    return Experiment(hashlib.sha256(raw).hexdigest(), len(train), len(holdout),
                      baseline_brier, candidate_brier)

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument("trades_csv", help="Precomputed point-in-time features with closed-trade labels")
    args = parser.parse_args()
    print(json.dumps(evaluate_csv(args.trades_csv).__dict__, indent=2))
