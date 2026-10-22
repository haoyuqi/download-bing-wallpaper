"""Verify badge publishing rejects incomplete and stale runs without network I/O."""

import base64
import io
import json
import os
from pathlib import Path
import runpy
import tempfile
import unittest
from unittest.mock import patch

SCRIPT = Path(__file__).with_name("publish-coverage-badge.py")


class PublishCoverageBadgeTest(unittest.TestCase):
    def publish(self, metrics, head="current", branch_exists=True):
        calls = []

        def response(request, timeout):
            body = json.loads(request.data) if request.data else None
            calls.append((request.method, request.full_url, body))
            if request.full_url.endswith("git/ref/heads/master"):
                data = {"object": {"sha": head}}
            elif request.full_url.endswith("git/ref/heads/coverage-badge"):
                if not branch_exists:
                    from urllib.error import HTTPError
                    raise HTTPError(request.full_url, 404, "Not Found", {}, None)
                data = {"object": {"sha": "badge"}}
            elif request.method == "GET" and "/contents/coverage.json" in request.full_url:
                data = {"sha": "previous", "content": base64.b64encode(b"{}").decode()}
            else:
                data = {}
            return io.BytesIO(json.dumps(data).encode())

        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "metrics.json"
            path.write_text(json.dumps(metrics))
            with patch.dict(os.environ, {"GITHUB_REPOSITORY": "example/package", "GH_TOKEN": "test-placeholder",
                                          "SOURCE_SHA": "current", "SOURCE_BRANCH": "master"}), \
                    patch("sys.argv", [str(SCRIPT), str(path)]), \
                    patch("urllib.request.urlopen", side_effect=response), \
                    patch("sys.stdout", new_callable=io.StringIO):
                try:
                    runpy.run_path(str(SCRIPT), run_name="__main__")
                except SystemExit as error:
                    if error.code not in (0, None):
                        raise
        return calls

    def test_rejects_rounded_or_invalid_counters_before_any_api_call(self):
        for total, covered in ((1000000, 999999), (0, 0), (True, True), ("2", "2")):
            with self.subTest(total=total), self.assertRaises(SystemExit):
                self.publish({"coverage": "100.00%", "total_statements": total, "covered_statements": covered})

    def test_stale_run_does_not_write(self):
        calls = self.publish({"coverage": "100.00%", "total_statements": 235, "covered_statements": 235}, head="newer")
        self.assertEqual([call[0] for call in calls], ["GET"])

    def test_publishes_measured_coverage_to_data_branch(self):
        calls = self.publish({"coverage": "100.00%", "total_statements": 235, "covered_statements": 235})
        writes = [body for method, _, body in calls if method == "PUT"]
        self.assertEqual(len(writes), 1)
        self.assertEqual(writes[0]["branch"], "coverage-badge")
        badge = json.loads(base64.b64decode(writes[0]["content"]))
        self.assertEqual(badge["message"], "100.00%")

    def test_creates_missing_data_branch_from_tested_commit(self):
        calls = self.publish({"coverage": "100.00%", "total_statements": 235, "covered_statements": 235}, branch_exists=False)
        creates = [body for method, _, body in calls if method == "POST"]
        self.assertEqual(creates, [{"ref": "refs/heads/coverage-badge", "sha": "current"}])


if __name__ == "__main__":
    unittest.main()
