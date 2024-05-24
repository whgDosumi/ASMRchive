from main import *

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

# Tests get_pfp function in main
def can_get_pfp(channel_url):
    try:
        pfp_url = get_pfp(channel_url)
        requests.get(pfp_url)
        return True, "Successfully found pfp for channel_url"
    except Exception as e:
        return False, str(e)

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
    

if __name__ == "__main__":
    # Add your tests to this dict, function_name: (args,)
    tests = {
        can_get_pfp: ("https://www.youtube.com/channel/UC1kvM3pZGg3QaSQBS91Cwzg",), 
    }

    run_tests(tests)