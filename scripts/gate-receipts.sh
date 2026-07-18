#!/usr/bin/env bash

assestme_gate_receipt_path() {
    local gate="$1"
    local project_dir="$2"

    printf '%s/storage/framework/cache/assestme-%s-gate.receipt\n' "$project_dir" "$gate"
}

assestme_gate_fingerprint() {
    local gate="$1"
    local project_dir="$2"

    (
        cd "$project_dir"

        printf 'assestme-gate-receipt-v1\0%s\0' "$gate"
        git rev-parse HEAD
        git diff --no-ext-diff --binary HEAD -- . ':(exclude)plan.md'

        if [[ -f plan.md ]]; then
            sed '/^## 22\. Progress/,$d' plan.md | sha256sum
        fi

        while IFS= read -r -d '' path; do
            if [[ "$path" == storage/framework/cache/assestme-*-gate.receipt* ]]; then
                continue
            fi

            printf 'untracked\0%s\0' "$path"
            sha256sum -- "$path"
        done < <(git ls-files --others --exclude-standard -z)

        php -v
        php -m | LC_ALL=C sort
        composer --version 2>&1

        for dependency_state in composer.json composer.lock vendor/composer/installed.json; do
            if [[ -f "$dependency_state" ]]; then
                sha256sum -- "$dependency_state"
            fi
        done
    ) | sha256sum | awk '{ print $1 }'
}

assestme_gate_receipt_matches() {
    local gate="$1"
    local project_dir="$2"
    local receipt_path
    local expected_fingerprint

    receipt_path="$(assestme_gate_receipt_path "$gate" "$project_dir")"
    [[ -f "$receipt_path" ]] || return 1

    expected_fingerprint="$(assestme_gate_fingerprint "$gate" "$project_dir")"
    [[ "$(<"$receipt_path")" == "$expected_fingerprint" ]]
}

assestme_write_gate_receipt() {
    local gate="$1"
    local project_dir="$2"
    local fingerprint="$3"
    local receipt_path
    local temporary_path

    receipt_path="$(assestme_gate_receipt_path "$gate" "$project_dir")"
    temporary_path="${receipt_path}.$$"
    mkdir -p "$(dirname "$receipt_path")"
    umask 077
    printf '%s\n' "$fingerprint" > "$temporary_path"
    mv "$temporary_path" "$receipt_path"
}

assestme_clear_gate_receipt() {
    local gate="$1"
    local project_dir="$2"
    local receipt_path

    receipt_path="$(assestme_gate_receipt_path "$gate" "$project_dir")"
    rm -f -- "$receipt_path"
}
