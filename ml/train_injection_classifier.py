"""
Model 1: Prompt injection classifier.
Trained on hf_prompt_injections.csv (11,598 rows, text -> label).
This is the one dataset that's genuinely training-ready as-is.
"""
import pandas as pd
import joblib
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.model_selection import train_test_split
from sklearn.metrics import classification_report, roc_auc_score

import config

df = pd.read_csv(config.HF_PROMPT_INJECTIONS_CSV)

X_train, X_test, y_train, y_test = train_test_split(
    df['text'], df['label'], test_size=0.2, random_state=42, stratify=df['label']
)

vectorizer = TfidfVectorizer(max_features=20000, ngram_range=(1, 2), min_df=2)
X_train_vec = vectorizer.fit_transform(X_train)
X_test_vec = vectorizer.transform(X_test)

clf = LogisticRegression(max_iter=1000, class_weight='balanced')
clf.fit(X_train_vec, y_train)

preds = clf.predict(X_test_vec)
probs = clf.predict_proba(X_test_vec)[:, 1]

print(f"=== Held-out test set performance ({len(y_test)} rows) ===")
print(classification_report(y_test, preds, target_names=['benign', 'injection']))
print(f"ROC-AUC: {roc_auc_score(y_test, probs):.4f}")

joblib.dump(vectorizer, config.VECTORIZER_PATH)
joblib.dump(clf, config.CLASSIFIER_PATH)
print(f"\nSaved: {config.VECTORIZER_PATH}, {config.CLASSIFIER_PATH}")