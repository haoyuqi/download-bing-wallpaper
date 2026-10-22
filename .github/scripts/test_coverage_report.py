"""Regression cases for the raw-counter coverage gate."""

import importlib.util
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location("coverage_report", Path(__file__).with_name("coverage-report.py"))
report = importlib.util.module_from_spec(spec)
spec.loader.exec_module(report)


class CoverageGateTest(unittest.TestCase):
    def load(self, total="2", covered="2", file_total=None):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "clover.xml"
            path.write_text(
                f'<coverage><project><file name="src/Example.php"><metrics statements="{file_total or total}" '
                f'coveredstatements="{covered}"/></file><metrics statements="{total}" '
                f'coveredstatements="{covered}"/></project></coverage>'
            )
            return report.read_coverage(path)

    def test_accepts_complete_coverage(self):
        report.require_complete(self.load())

    def test_rejects_uncovered_line(self):
        with self.assertRaisesRegex(ValueError, "exactly 100%"):
            report.require_complete(self.load(covered="1"))

    def test_rejects_rounded_100_percent(self):
        metrics = self.load(total="1000000", covered="999999")
        self.assertEqual(metrics["coverage"], "100.00%")
        with self.assertRaises(ValueError):
            report.require_complete(metrics)

    def test_rejects_invalid_or_missing_counters(self):
        for invalid in ("", "-1", "1_000", "2.0", "NaN", "٢"):
            with self.subTest(invalid=invalid), self.assertRaises(ValueError):
                self.load(covered=invalid)

    def test_rejects_empty_or_impossible_totals(self):
        for total, covered in (("0", "0"), ("2", "3")):
            with self.subTest(total=total), self.assertRaises(ValueError):
                self.load(total, covered)

    def test_rejects_inconsistent_source_totals(self):
        with self.assertRaisesRegex(ValueError, "disagree"):
            self.load(file_total="3")

    def test_rejects_missing_report(self):
        with self.assertRaises(OSError):
            report.read_coverage("/nonexistent/clover.xml")


if __name__ == "__main__":
    unittest.main()
