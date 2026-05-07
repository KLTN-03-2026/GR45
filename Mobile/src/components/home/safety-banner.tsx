import MaterialIcons from "@expo/vector-icons/MaterialIcons";
import { LinearGradient } from "expo-linear-gradient";
import { StyleSheet, Text, View } from "react-native";

export function SafetyBanner() {
  return (
    <LinearGradient
      colors={["#0052cc", "#003d99"]}
      style={styles.container}
      start={{ x: 0, y: 0 }}
      end={{ x: 1, y: 0 }}
    >
      <View style={styles.iconContainer}>
        <MaterialIcons name="health-and-safety" size={32} color="#ffffff" />
      </View>
      <View style={styles.textContainer}>
        <Text style={styles.title}>Hành trình an toàn AI</Text>
        <Text style={styles.desc}>
          Hệ thống tự động phát hiện mệt mỏi tài xế và kiểm soát tốc độ 24/7.
        </Text>
      </View>
      <MaterialIcons
        name="shield"
        size={120}
        color="rgba(255,255,255,0.05)"
        style={styles.bgIcon}
      />
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  container: {
    marginHorizontal: 20,
    borderRadius: 16,
    padding: 20,
    flexDirection: "row",
    alignItems: "center",
    gap: 16,
    overflow: "hidden",
  },
  iconContainer: {
    width: 60,
    height: 60,
    borderRadius: 16,
    backgroundColor: "rgba(255,255,255,0.2)",
    justifyContent: "center",
    alignItems: "center",
  },
  textContainer: {
    flex: 1,
    zIndex: 1,
  },
  title: {
    fontSize: 16,
    fontWeight: "800",
    color: "#ffffff",
    marginBottom: 6,
  },
  desc: {
    fontSize: 12,
    color: "#e2e8f0",
    lineHeight: 18,
  },
  bgIcon: {
    position: "absolute",
    right: -20,
    bottom: -20,
    zIndex: 0,
  },
});
