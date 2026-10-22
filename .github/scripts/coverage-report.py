"""Validate exact Clover line coverage and summarize this installed test run."""

import json
import os
from pathlib import Path
import re
import sys
import xml.etree.ElementTree as ET


def counter(metrics, name):
    value = metrics.get(name, "")
    if re.fullmatch(r"[0-9]+", value) is None:
        raise ValueError(f"Missing or invalid {name} counter")
    return int(value)


def read_coverage(path):
    root = ET.parse(path).getroot()
    metrics = root.find("./project/metrics")
    files = root.findall("./project//file")
    if metrics is None or not files:
        raise ValueError("Clover report has no project metrics or source files")
    total = counter(metrics, "statements")
    covered = counter(metrics, "coveredstatements")
    if total <= 0 or covered > total:
        raise ValueError("Invalid executable line totals")
    file_total = file_covered = 0
    for file in files:
        counts = file.find("metrics")
        if counts is None:
            raise ValueError("Missing source file metrics")
        statements = counter(counts, "statements")
        hits = counter(counts, "coveredstatements")
        if hits > statements:
            raise ValueError("Invalid source file line totals")
        file_total += statements
        file_covered += hits
    if (file_total, file_covered) != (total, covered):
        raise ValueError("Project and source file counters disagree")
    return {"covered_statements": covered, "total_statements": total,
            "files": len(files), "coverage": f"{covered / total * 100:.2f}%"}


def require_complete(metrics):
    if metrics["covered_statements"] != metrics["total_statements"]:
        raise ValueError("Executable line coverage must be exactly 100% "
                         f'({metrics["covered_statements"]}/{metrics["total_statements"]})')


def main():
    metrics = read_coverage(sys.argv[1])
    suite = ET.parse(sys.argv[2]).getroot().find("testsuite")
    if suite is None:
        raise ValueError("Missing JUnit test suite")
    tests = counter(suite, "tests")
    failures = counter(suite, "failures") + counter(suite, "errors")
    skipped = counter(suite, "skipped")
    runtime = json.loads(Path(sys.argv[3]).read_text())
    for key in ("php", "laravel", "phpunit", "composer"):
        if not isinstance(runtime.get(key), str) or not re.fullmatch(r"[0-9][0-9A-Za-z.+-]*", runtime[key]):
            raise ValueError(f"Missing or invalid installed {key} version")
    metrics.update(runtime, tests=tests, failures=failures, skipped=skipped)
    Path(sys.argv[4]).write_text(json.dumps(metrics) + "\n")
    complete = metrics["covered_statements"] == metrics["total_statements"]
    success = complete and tests > 0 and failures == 0 and skipped == 0
    summary = (
        "## Package test report\n\n"
        f"**{'● Passed' if success else '◉ Failed'}**\n\n"
        "| Metric | Result |\n| --- | --- |\n"
        f"| Tests | {tests} |\n| Failures / errors | {failures} |\n| Skipped | {skipped} |\n"
        f'| Line coverage | {metrics["coverage"]} ({metrics["covered_statements"]}/{metrics["total_statements"]}) |\n'
        f'| Source files | {metrics["files"]} |\n'
        + "".join(f"| {label} | {runtime[key]} |\n" for key, label in (
            ("php", "PHP"), ("laravel", "Laravel"), ("phpunit", "PHPUnit"), ("composer", "Composer")))
        + f'| Test duration | {suite.get("time", "unknown")} s |\n'
        "\nScope: complete `src/` tree, including unexecuted files. Required line coverage: **100%**.\n"
    )
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a") as output:
            output.write(summary)
    print(summary)
    require_complete(metrics)
    if not success:
        raise ValueError("Tests must run without failures, errors, or skipped cases")


if __name__ == "__main__":
    try:
        main()
    except (ValueError, ET.ParseError, OSError) as error:
        raise SystemExit(str(error)) from error
