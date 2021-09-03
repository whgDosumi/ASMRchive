from main import channel

exit = False
while not exit:
    alias = input("Enter alias: ")
    channel_id = input("Enter channel ID: ")
    status = "new"
    new_channel = channel(alias, channel_id, status)
    new_channel.save()
    cont = input("Continue (no to exit)? : ")
    if cont == "no":
        exit = True
