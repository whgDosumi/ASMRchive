import subprocess
import time
import requests

def print_green(text):
    green = "\033[92m"
    reset = "\033[0m"
    print(f"{green}{text}{reset}")

def print_red(text):
    red = "\033[91m"
    reset = "\033[0m"
    print(f"{red}{text}{reset}")

class TestResult:
    def __init__(self, name, passed, message) -> None:
        self.name = name
        self.passed = passed
        self.message = message

def check_ytdlp():
    result = subprocess.run(["yt-dlp", "-U"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    result_text = result.stdout.strip()
    if ("yt-dlp is up to date" in result_text.lower()):
        return True, result_text
    return False, result_text

# Tests get_pfp function in main
def can_get_pfp(channel_url, max_tries=5):
    from main import get_pfp
    tries = 0
    while tries < max_tries:
        try:
            pfp_url = get_pfp(channel_url)
            requests.get(pfp_url)
            return True, "Successfully found pfp for channel_url"
        except Exception as e:
            print(str(e))
    return False

def print_results(results):
    print("\n\n-----\nTest Results:")
    for result in results:
        status = "PASSED" if result.passed else "FAILED"
        if result.passed:
            status = "PASSED"
            print_green(f"{result.name}: {status}")
        else:
            status = "FAILED"
            print_red(f"{result.name}: {status}")
            print_red(f"  Reason: {result.message}")
    print("-----\n\n")

def can_get_apple_touch_icon(url, max_tries=5, wait_for=5):
    # Give us a few tries, while we wait for the webserver to load up.

    # max_tries specifies how many times it'll try before failing.
    # wait_for specifies how many seconds it will wait before trying again.
    tries = 0
    success = False
    while tries < max_tries:
        tries += 1
        try:
            requests.get(url)
            return True, "Successfully loaded the apple touch icon."
        except Exception as e:
            time.sleep(wait_for)
    return False, str(e)

def run_tests(tests):
    results = []
    for test, args in tests.items():
        try:
            passed, message = test(*args)
            results.append(TestResult(test.__name__, passed, message))
        except Exception as e:
            results.append(TestResult(test.__name__, False, f"Exception: {e}"))
    
    print_results(results)

    if any(not result.passed for result in results):
        raise Exception("Some tests failed")
    

def wait_for_webserver(url="http://127.0.0.1/", max_retries=30, delay=2):
    print(f"Waiting for container to finish startup at {url}...")
    for i in range(max_retries):
        try:
            requests.get(url, timeout=5)
            print_green("Container is up!")
            return
        except requests.exceptions.RequestException:
            time.sleep(delay)
    raise Exception(f"Failed to connect to {url} after {max_retries} retries")

if __name__ == "__main__":
    wait_for_webserver()

    # Add your tests to this dict, function_name: (args,)
    tests = {
        can_get_pfp: ("https://www.youtube.com/channel/UC1kvM3pZGg3QaSQBS91Cwzg",),
        check_ytdlp: (),
        can_get_apple_touch_icon: ("http://127.0.0.1/ASMRchive/apple_touch_icon.png",),
    }

    run_tests(tests)