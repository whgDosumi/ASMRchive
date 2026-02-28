import os

from main import get_my_folder

standard_keywords = ["asmr", "binaural", "ku100", "3dio", "バイノーラル", "Ear Cleaning"]

def new_folder(path):
    if not os.path.exists(path):
        os.makedirs(path)

whereami = get_my_folder()
new_folder(os.path.join(whereami, "cookies"))
new_folder(os.path.join(whereami, "recordings"))

print("\n\nStandard keywords for ASMR (not case sensitive):\n")
for i in standard_keywords:
    print("\"" + i + "\"")
use_keywords = None
while use_keywords != True and use_keywords != False:
    use_keywords = input("\n Use these keywords(y/n)?")
    if use_keywords.lower() in ["y", "yes", "true", "t"]:
        use_keywords = True
        break
    elif use_keywords.lower() in ["n", "no", "false", "f"]:
        use_keywords = False
        break
    else:
        print("Unrecognized input, try again (y/n)")

if use_keywords:
    desired_keywords = standard_keywords
else:
    desired_keywords = []

print("\nAdd new keywords below, enter nothing and press enter to finish.")
user_in = "none"
while user_in != "":
    user_in = input("New keyword: ")
    if not user_in == "":
        desired_keywords.append(user_in)

print("\nWriting keywords to keywords.txt, you can add new entires to this manually if desired...")
with open(os.path.join(whereami, "keywords.txt"), "w") as key_file:
    wrote = False
    for key in desired_keywords:
        if wrote:
            key_file.write("\n" + str(key))
        else:
            key_file.write(str(key))
            wrote = True

print("\n\nInitial config complete. Remember to add some channels, using add_channels.py")