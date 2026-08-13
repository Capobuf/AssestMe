#!/usr/bin/env bash
set -Eeuo pipefail

info() {
    printf 'INFO: %s\n' "$*"
}

project_dir="${1:-$PWD}"
cd "$project_dir"

bash scripts/verify-core.sh "$project_dir"

if [[ "${RUN_DUSK:-1}" == "1" ]]; then
    source scripts/gate-receipts.sh

    if assestme_gate_receipt_matches browser "$project_dir"; then
        info "Reusing the successful browser gate for the exact current repository and runtime fingerprint."
    else
        info "No matching browser receipt was found; running the isolated browser gate once."
        scripts/dusk-isolated.sh "$project_dir"
    fi
else
    info "Dusk was explicitly disabled with RUN_DUSK=${RUN_DUSK}."
fi

info "Complete acceptance verification passed."
