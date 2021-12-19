from main import Channel

#literally the simplest little app for adding new channels to ASMRchive. 

exit = False
while not exit:
    output_directory = "" #needed for class
    alias = input("Enter Channel Name (This is how it shows on web): ")
    channel_id = "?"
    while channel_id == "?":
        channel_id = input("Enter channel ID (? for help): ")
        if channel_id == "?":
            print("Channel ID is the ID listed at the end of the channel url")
            print("Example: for this channel - https://www.youtube.com/channel/UCMwGHR0BTZuLsmjY_NT5Pwg")
            print("The channel ID would be: UCMwGHR0BTZuLsmjY_NT5Pwg")
    status = "new"
    new_channel = Channel(alias, channel_id, status, output_directory)
    new_channel.save()
    cont = input("Continue (no to exit)? : ")
    if cont == "no":
        exit = True
