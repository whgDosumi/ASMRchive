from main import Channel

exit = False
while not exit:
    output_directory = "/mnt/thicc/ASMRchive"
    alias = input("Enter alias: ")
    channel_id = input("Enter channel ID: ")
    status = "new"
    new_channel = Channel(alias, channel_id, status, output_directory)
    new_channel.save()
    cont = input("Continue (no to exit)? : ")
    if cont == "no":
        exit = True
